<?php

declare(strict_types=1);

use App\Actions\Supervisors\UpdateSupervisorPermissions;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| PUT /admin/supervisors/{id}/permissions — الفحص على الخادم (T03 — docs/SPEC.md §10)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->target = User::factory()->supervisor()->withPermissions(['users.view'])->create();
});

function updatePermissionsUrl(User $supervisor): string
{
    return route('admin.supervisors.permissions.update', $supervisor);
}

test('المدير يعدّل الصلاحيات عبر المسار', function (): void {
    $this->actingAs($this->admin)
        ->from('/admin')
        ->put(updatePermissionsUrl($this->target), ['permissions' => ['stats.view']])
        ->assertRedirect('/admin')
        ->assertSessionHas('status', 'تم تحديث الصلاحيات.');

    expect($this->target->fresh()->permissions->pluck('name')->all())->toBe(['stats.view']);
});

test('الوصول المباشر بالرابط لمشرف بلا supervisors.manage يُرفض 403', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view', 'stats.view'])->create();

    $this->actingAs($supervisor)
        ->put(updatePermissionsUrl($this->target), ['permissions' => ['stats.view']])
        ->assertForbidden();

    expect($this->target->fresh()->permissions->pluck('name')->all())->toBe(['users.view']);
});

test('المبادر يُرفض 403', function (): void {
    $this->actingAs(User::factory()->create())
        ->put(updatePermissionsUrl($this->target), ['permissions' => []])
        ->assertForbidden();
});

test('الزائر يُحوَّل إلى الدخول', function (): void {
    $this->put(updatePermissionsUrl($this->target), ['permissions' => []])
        ->assertRedirect(route('login'));
});

test('المشرف يعدّل صلاحيات نفسه عبر الرابط فيُرفض 403', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage'])->create();

    $this->actingAs($manager)
        ->put(updatePermissionsUrl($manager), ['permissions' => ['supervisors.manage', 'settings.manage']])
        ->assertForbidden();

    expect($manager->fresh()->permissions->pluck('name')->all())->toBe(['supervisors.manage']);
});

test('المشرف يمنح عبر الرابط صلاحية لا يملكها فيُرفض 403', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage'])->create();

    $this->actingAs($manager)
        ->put(updatePermissionsUrl($this->target), ['permissions' => ['users.view', 'beneficiaries.manage']])
        ->assertForbidden();

    expect($this->target->fresh()->permissions->pluck('name')->all())->toBe(['users.view']);
});

test('محاولة سحب صلاحيات آخر مدير عبر الرابط تُرفض 403', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage'])->create();

    $this->actingAs($manager)
        ->put(updatePermissionsUrl($this->admin), ['permissions' => []])
        ->assertForbidden();

    expect($this->admin->fresh()->can('supervisors.manage'))->toBeTrue();
});

test('صلاحية تابعة دون recovery.handle تُرفض بخطأ تحقق', function (): void {
    $this->actingAs($this->admin)
        ->put(updatePermissionsUrl($this->target), ['permissions' => ['recovery.change_phone']])
        ->assertSessionHasErrors('permissions');
});

test('سحب الصلاحية يسري فورًا في الطلب التالي', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage', 'users.view'])->create();

    $this->actingAs($manager)
        ->put(updatePermissionsUrl($this->target), ['permissions' => ['users.view']])
        ->assertRedirect();

    app(UpdateSupervisorPermissions::class)->handle($this->admin, $manager->fresh(), ['users.view']);

    $this->actingAs($manager->fresh())
        ->put(updatePermissionsUrl($this->target), ['permissions' => []])
        ->assertForbidden();
});

test('تعطيل المشرف يسري فورًا في الطلب التالي', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage', 'users.view'])->create();

    $manager->update(['is_active' => false]);

    $this->actingAs($manager->fresh())
        ->put(updatePermissionsUrl($this->target), ['permissions' => []])
        ->assertForbidden();
});
