<?php

declare(strict_types=1);

use App\Models\User;
use App\PermissionKey;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Permission;

/*
|--------------------------------------------------------------------------
| مفاتيح الصلاحيات وفحصها على الخادم (T03 — docs/SPEC.md §2, §12.5)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

test('البذر ينشئ المفاتيح السبعة عشر على حارس web، وتكراره آمن', function (): void {
    $this->seed(PermissionSeeder::class);

    expect(Permission::query()->where('guard_name', 'web')->pluck('name')->sort()->values()->all())
        ->toBe(collect([
            'stats.view', 'transfers.view', 'transfers.review', 'transfers.assign', 'users.view',
            'users.suspend', 'recovery.handle', 'recovery.other_number', 'recovery.change_phone',
            'security.view', 'stats.recovery', 'settings.manage', 'beneficiaries.manage',
            'content.manage', 'messages.view', 'converter.use', 'supervisors.manage',
        ])->sort()->values()->all())
        ->and(Permission::query()->count())->toBe(17);
});

test('SENSITIVE_PERMISSIONS تطابق قائمة المهمة', function (): void {
    expect(array_map(fn (PermissionKey $key): string => $key->value, PermissionKey::SENSITIVE_PERMISSIONS))
        ->toBe([
            'recovery.other_number', 'recovery.change_phone', 'beneficiaries.manage',
            'settings.manage', 'content.manage', 'supervisors.manage',
        ])
        ->and(PermissionKey::SupervisorsManage->isSensitive())->toBeTrue()
        ->and(PermissionKey::UsersView->isSensitive())->toBeFalse();
});

test('المدير يملك كل الصلاحيات ضمنيًا دون منح مباشر', function (): void {
    $admin = User::factory()->admin()->create();

    expect($admin->permissions)->toBeEmpty();

    foreach (PermissionKey::values() as $permission) {
        expect($admin->can($permission))->toBeTrue();
    }
});

test('المشرف يملك ما مُنح له مباشرة فقط', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view'])->create();

    expect($supervisor->can('users.view'))->toBeTrue()
        ->and($supervisor->can('users.suspend'))->toBeFalse()
        ->and($supervisor->can('supervisors.manage'))->toBeFalse();
});

test('المبادر لا يملك أي صلاحية إدارية', function (): void {
    $user = User::factory()->create();

    foreach (PermissionKey::values() as $permission) {
        expect($user->can($permission))->toBeFalse();
    }
});

test('الحساب المعطَّل يفقد كل صلاحياته فورًا، مديرًا كان أو مشرفًا', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view'])->create();
    User::factory()->admin()->create();
    $admin = User::factory()->admin()->create();

    $supervisor->update(['is_active' => false]);
    $admin->update(['is_active' => false]);

    expect($supervisor->fresh()->can('users.view'))->toBeFalse()
        ->and($admin->fresh()->can('settings.manage'))->toBeFalse();
});

test('الصلاحية التابعة لا تعمل دون recovery.handle حتى لو وُجدت في قاعدة البيانات', function (string $permission): void {
    $supervisor = User::factory()->supervisor()->withPermissions([$permission])->create();

    expect($supervisor->can($permission))->toBeFalse();

    $supervisor->givePermissionTo('recovery.handle');

    expect($supervisor->fresh()->can($permission))->toBeTrue();
})->with(['recovery.other_number', 'recovery.change_phone']);

test('مفتاح غير موجود لا يُمنح لأحد عدا المدير', function (): void {
    $supervisor = User::factory()->supervisor()->create();

    expect($supervisor->can('not.a.permission'))->toBeFalse();
});
