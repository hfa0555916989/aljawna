<?php

declare(strict_types=1);

use App\Actions\Beneficiaries\ApproveBeneficiary;
use App\Actions\Beneficiaries\ChangeBeneficiaryStatus;
use App\BeneficiaryStatus;
use App\Filament\Resources\Beneficiaries\Pages\EditBeneficiary;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| الاعتماد والإغلاق وإعادة الفتح (T05 — FR-29, FR-32)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('الاعتماد بعد المراجعة يُظهر المبادرة للعامة ويسجّل من اعتمدها ومتى', function (): void {
    $beneficiary = Beneficiary::factory()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionVisible('approve')
        ->callAction('approve')
        ->assertNotified('اعتُمدت المبادرة.');

    $beneficiary->refresh();

    expect($beneficiary->isApproved())->toBeTrue()
        ->and($beneficiary->approved_by)->toBe($this->admin->id)
        ->and(Beneficiary::public()->whereKey($beneficiary->id)->exists())->toBeTrue();
});

test('الاعتماد يُكتب في سجل التدقيق باسم من اعتمد', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create();
    $beneficiary = Beneficiary::factory()->create();
    $this->actingAs($supervisor);

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->callAction('approve');

    $audit = AuditLog::query()->sole();

    expect($audit->action)->toBe(ApproveBeneficiary::AUDIT_ACTION)
        ->and($audit->actor_id)->toBe($supervisor->id)
        ->and($audit->subject_type)->toBe($beneficiary->getMorphClass())
        ->and($audit->subject_id)->toBe($beneficiary->id);
});

test('زر الاعتماد يختفي بعد الاعتماد', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionHidden('approve');
});

test('الاعتماد يتطلب نافذة تأكيد', function (): void {
    $beneficiary = Beneficiary::factory()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->mountAction('approve')
        ->assertMountedActionModalSee('بعد الاعتماد تظهر المبادرة للعامة');

    expect($beneficiary->refresh()->isApproved())->toBeFalse();
});

test('إغلاق المبادرة يوقف استقبال الحوالات ويبقيها في القائمة العامة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionHidden('reopen')
        ->callAction('close')
        ->assertNotified('أُغلقت المبادرة.');

    $beneficiary->refresh();

    expect($beneficiary->status->value)->toBe('closed')
        ->and($beneficiary->acceptsTransfers())->toBeFalse()
        ->and(Beneficiary::available()->whereKey($beneficiary->id)->exists())->toBeFalse()
        ->and(Beneficiary::public()->whereKey($beneficiary->id)->exists())->toBeTrue();
});

test('إعادة فتح المبادرة المغلقة تعيدها متاحة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->closed()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionHidden('close')
        ->callAction('reopen')
        ->assertNotified('أُعيد فتح المبادرة.');

    expect($beneficiary->refresh()->acceptsTransfers())->toBeTrue();
});

test('الإغلاق وإعادة الفتح يُكتبان في سجل التدقيق باسم من نفّذ', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create();
    $page = Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()]);

    $page->callAction('close');

    $closed = AuditLog::query()->sole();

    expect($closed->action)->toBe(ChangeBeneficiaryStatus::AUDIT_CLOSED)
        ->and($closed->actor_id)->toBe($this->admin->id)
        ->and($closed->subject_id)->toBe($beneficiary->id);

    $page->callAction('reopen');

    $reopened = AuditLog::query()->latest('id')->first();

    expect(AuditLog::query()->count())->toBe(2)
        ->and($reopened->action)->toBe(ChangeBeneficiaryStatus::AUDIT_REOPENED)
        ->and($reopened->actor_id)->toBe($this->admin->id)
        ->and($reopened->subject_id)->toBe($beneficiary->id);
});

test('طلب حالة هي الحالة الحالية لا يغيّر شيئًا ولا يُسجَّل', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create();

    app(ChangeBeneficiaryStatus::class)->handle($this->admin, $beneficiary, BeneficiaryStatus::Active);

    expect(AuditLog::query()->exists())->toBeFalse();
});

test('لا يوجد حذف للمستفيدين', function (): void {
    $beneficiary = Beneficiary::factory()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionDoesNotExist('delete');
});
