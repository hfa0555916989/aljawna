<?php

declare(strict_types=1);

use App\Actions\Transfers\AssignTransferReview;
use App\Actions\Transfers\CommentOnTransfer;
use App\Actions\Transfers\MatchTransfer;
use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use App\TransferReviewState;
use Database\Seeders\PermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| مراجعة الحوالات والإسناد (T09 — FR-43..48)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->reviewer = User::factory()->supervisor()->withPermissions(['transfers.view', 'transfers.review'])->create();
    $this->assigner = User::factory()->supervisor()->withPermissions(['transfers.view', 'transfers.assign'])->create();
    $this->beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);
});

test('المدير ومن يملك transfers.assign يسندان لمشرف مؤهل', function (string $who): void {
    $actor = $who === 'admin' ? $this->admin : $this->assigner;
    $transfer = Transfer::factory()->for($this->beneficiary)->create();

    expect($actor->can('assign', [$transfer, $this->reviewer]))->toBeTrue();

    app(AssignTransferReview::class)->handle($actor, $transfer, $this->reviewer);

    expect($transfer->fresh()->assigned_to)->toBe($this->reviewer->id)
        ->and(AuditLog::query()->where('action', 'transfer.assigned')->exists())->toBeTrue();
})->with(['admin', 'assigner']);

test('نافذة الإسناد تُفتح من الجدول وتعرض المشرفين المؤهلين فقط ثم تُسند', function (): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->create();
    $unqualified = User::factory()->supervisor()->withPermissions(['transfers.view'])->create();
    $assign = TestAction::make('assign')->table($transfer);

    Livewire::actingAs($this->assigner)->test(ListTransfers::class)
        ->mountAction($assign)
        ->assertActionMounted($assign)
        ->assertMountedActionModalSee($this->reviewer->full_name)
        ->assertMountedActionModalDontSee($unqualified->full_name)
        ->setActionData(['assignee_id' => $this->reviewer->id])
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified(__('transfers.review.assigned'));

    expect($transfer->fresh()->assigned_to)->toBe($this->reviewer->id);
});

test('من لا يملك transfers.assign لا يسند', function (): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->create();
    $viewer = User::factory()->supervisor()->withPermissions(['transfers.view'])->create();

    expect($viewer->can('assign', [$transfer, $this->reviewer]))->toBeFalse()
        ->and($this->reviewer->can('assign', [$transfer, $this->reviewer]))->toBeFalse();
});

test('إسناد لمشرف غير مؤهل يُرفض', function (User $assignee): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->create();

    expect($this->admin->can('assign', [$transfer, $assignee]))->toBeFalse();

    expect(fn () => app(AssignTransferReview::class)->handle($this->admin, $transfer, $assignee))
        ->toThrow(AuthorizationException::class);
})->with([
    'بلا صلاحية المراجعة' => fn () => User::factory()->supervisor()->withPermissions(['transfers.view'])->create(),
    'معطّل' => fn () => User::factory()->supervisor()->inactive()->withPermissions(['transfers.review'])->create(),
    'مبادر' => fn () => User::factory()->create(),
    'مدير' => fn () => User::factory()->admin()->create(),
]);

test('التعليق للمسنَد إليه على المتكررة فقط', function (): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->repeated()->create();
    app(AssignTransferReview::class)->handle($this->admin, $transfer, $this->reviewer);
    $transfer->refresh();

    expect($this->reviewer->can('comment', $transfer))->toBeTrue()
        ->and($this->admin->can('comment', $transfer))->toBeFalse()
        ->and($this->assigner->can('comment', $transfer))->toBeFalse();
});

test('تعليق على حوالة غير متكررة يُرفض', function (): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->create(['is_repeated' => false]);
    app(AssignTransferReview::class)->handle($this->admin, $transfer, $this->reviewer);
    $transfer->refresh();

    expect($this->reviewer->can('comment', $transfer))->toBeFalse();

    expect(fn () => app(CommentOnTransfer::class)->handle($this->reviewer, $transfer, 'ملاحظة'))
        ->toThrow(AuthorizationException::class);
});

test('المسنَد إليه لا يطابق ولا يراجع نهائيًا قبل إسناد المراجعة النهائية', function (): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->repeated()->create();
    app(AssignTransferReview::class)->handle($this->admin, $transfer, $this->reviewer);
    $transfer->refresh();

    expect($this->reviewer->can('match', $transfer))->toBeFalse()
        ->and($this->reviewer->can('finalReview', $transfer))->toBeFalse()
        ->and($this->admin->can('match', $transfer))->toBeTrue()
        ->and($this->admin->can('finalReview', $transfer))->toBeFalse();
});

test('زر إسناد المراجعة النهائية لمن أسند بعد التعليق، والمراجعة النهائية للمشرف بعده فقط', function (): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->repeated()->create();
    app(AssignTransferReview::class)->handle($this->assigner, $transfer, $this->reviewer);
    $transfer->refresh();

    expect($this->assigner->can('assign', [$transfer, $this->reviewer]))->toBeTrue();

    app(CommentOnTransfer::class)->handle($this->reviewer, $transfer->fresh(), 'ملاحظة على التكرار');
    $transfer->refresh();

    expect($transfer->review_state)->toBe(TransferReviewState::Commented)
        ->and($this->assigner->can('assign', [$transfer, $this->reviewer]))->toBeTrue()
        ->and($this->admin->can('assign', [$transfer, $this->reviewer]))->toBeFalse()
        ->and($this->reviewer->can('finalReview', $transfer))->toBeFalse();

    app(AssignTransferReview::class)->handle($this->assigner, $transfer, $this->reviewer);
    $transfer->refresh();

    expect($this->reviewer->can('finalReview', $transfer))->toBeTrue()
        ->and($this->assigner->can('finalReview', $transfer))->toBeFalse()
        ->and($this->admin->can('finalReview', $transfer))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'transfer.final_assigned')->exists())->toBeTrue();
});

test('المطابقة لا تغيّر مجموع الحوالة', function (): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->create(['amount' => '250.00']);

    app(MatchTransfer::class)->handle($this->admin, $transfer);

    expect($transfer->fresh()->review_state)->toBe(TransferReviewState::Matched)
        ->and($this->beneficiary->fresh()->collectedAmount())->toBe('250.00')
        ->and(AuditLog::query()->where('action', 'transfer.matched')->exists())->toBeTrue();
});

test('من لا يملك transfers.view لا يرى قائمة الحوالات', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['users.view'])->create())
        ->get('/admin/transfers')
        ->assertForbidden();
});

test('زر المراجعة النهائية يظهر للمشرف بعد إسنادها ويختفي قبله', function (): void {
    $transfer = Transfer::factory()->for($this->beneficiary)->repeated()->create();
    app(AssignTransferReview::class)->handle($this->assigner, $transfer, $this->reviewer);
    app(CommentOnTransfer::class)->handle($this->reviewer, $transfer->fresh(), 'ملاحظة');

    $hidden = TestAction::make('finalReview')->table($transfer);

    Livewire::actingAs($this->reviewer)->test(ListTransfers::class)
        ->assertActionHidden($hidden);

    app(AssignTransferReview::class)->handle($this->assigner, $transfer->fresh(), $this->reviewer);

    $visible = TestAction::make('finalReview')->table($transfer->fresh());

    Livewire::actingAs($this->reviewer)->test(ListTransfers::class)
        ->assertActionVisible($visible)
        ->callAction($visible, ['final_note' => 'أُنجزت المراجعة'])
        ->assertNotified(__('transfers.review.final_done'));

    expect($transfer->fresh()->review_state)->toBe(TransferReviewState::FinalReviewed)
        ->and(AuditLog::query()->where('action', 'transfer.final_reviewed')->exists())->toBeTrue();
});
