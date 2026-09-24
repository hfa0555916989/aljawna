<?php

declare(strict_types=1);

use App\Actions\Beneficiaries\ApproveBeneficiary;
use App\Actions\Beneficiaries\ChangeBeneficiaryStatus;
use App\Actions\Beneficiaries\CreateBeneficiary;
use App\Actions\Beneficiaries\UpdateBeneficiary;
use App\BeneficiaryStatus;
use App\Filament\Resources\Beneficiaries\Pages\EditBeneficiary;
use App\Models\Beneficiary;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| إدارة المستفيدين بصلاحية beneficiaries.manage فقط (T05 — §2, §12.10)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');
});

dataset('beneficiary pages', [
    'القائمة' => fn (): string => '/admin/beneficiaries',
    'التسجيل' => fn (): string => '/admin/beneficiaries/create',
    'التعديل' => fn (): string => '/admin/beneficiaries/'.Beneficiary::factory()->create()->getKey().'/edit',
]);

test('المدير يفتح صفحات المستفيدين ويرى عنصرها في التنقل', function (string $url): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get($url)
        ->assertOk()
        ->assertSee('المستفيدون');
})->with('beneficiary pages');

test('المشرف الممنوح beneficiaries.manage يفتح صفحات المستفيدين', function (string $url): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create())
        ->get($url)
        ->assertOk();
})->with('beneficiary pages');

test('المشرف بلا beneficiaries.manage يُرفض بـ 403 حتى مع صلاحيات أخرى', function (string $url): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['stats.view', 'transfers.view'])->create())
        ->get($url)
        ->assertForbidden();
})->with('beneficiary pages');

test('المشرف بلا beneficiaries.manage لا يرى عنصر المستفيدين في التنقل', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['stats.view'])->create())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('/admin/beneficiaries', false);
});

test('المدير المعطَّل يُرفض بـ 403', function (): void {
    $this->actingAs(User::factory()->admin()->inactive()->create())
        ->get('/admin/beneficiaries')
        ->assertForbidden();
});

test('المبادر لا يصل إلى إدارة المستفيدين', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin/beneficiaries')
        ->assertRedirect(route('dashboard'));
});

test('إجراءات المستفيدين ترفض من لا يملك الصلاحية على الخادم مباشرة', function (Closure $attempt): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['stats.view'])->create();
    $beneficiary = Beneficiary::factory()->create();

    expect(fn () => $attempt($supervisor, $beneficiary))->toThrow(AuthorizationException::class);
})->with([
    'التسجيل' => fn (User $actor) => app(CreateBeneficiary::class)->handle($actor, beneficiaryFormData()),
    'التعديل' => fn (User $actor, Beneficiary $beneficiary) => app(UpdateBeneficiary::class)
        ->handle($actor, $beneficiary, ['display_name' => 'اسم آخر مختلف تمامًا'], bankChangeConfirmed: true),
    'الاعتماد' => fn (User $actor, Beneficiary $beneficiary) => app(ApproveBeneficiary::class)->handle($actor, $beneficiary),
    'الإغلاق' => fn (User $actor, Beneficiary $beneficiary) => app(ChangeBeneficiaryStatus::class)
        ->handle($actor, $beneficiary, BeneficiaryStatus::Closed),
]);

test('حذف المستفيد ممنوع على المدير نفسه إن حاوله مباشرة', function (string $ability): void {
    $admin = User::factory()->admin()->create();
    $beneficiary = Beneficiary::factory()->create();
    $target = str_ends_with($ability, 'Any') ? Beneficiary::class : $beneficiary;

    expect($admin->can('beneficiaries.manage'))->toBeTrue()
        ->and($admin->can($ability, $target))->toBeFalse()
        ->and(fn () => Gate::forUser($admin)->authorize($ability, $target))->toThrow(AuthorizationException::class);

    expect($beneficiary->fresh())->not->toBeNull();
})->with(['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny']);

test('منع الحذف لا يمسّ منح المدير الضمني لبقية الإجراءات', function (): void {
    $admin = User::factory()->admin()->create();
    $beneficiary = Beneficiary::factory()->create();

    expect($admin->can('update', $beneficiary))->toBeTrue()
        ->and($admin->can('create', Beneficiary::class))->toBeTrue();
});

test('سحب beneficiaries.manage يمنع الحفظ من صفحة تعديل مفتوحة', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create();
    $beneficiary = Beneficiary::factory()->create();
    $this->actingAs($supervisor);

    $page = Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->fillForm(['display_name' => 'اسم آخر مختلف تمامًا']);

    $supervisor->revokePermissionTo('beneficiaries.manage');

    $page->call('save')->assertForbidden();

    expect($beneficiary->refresh()->display_name)->not->toBe('اسم آخر مختلف تمامًا');
});
