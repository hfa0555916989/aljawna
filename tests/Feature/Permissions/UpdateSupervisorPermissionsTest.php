<?php

declare(strict_types=1);

use App\Actions\Supervisors\UpdateSupervisorPermissions;
use App\Models\AuditLog;
use App\Models\User;
use App\PermissionKey;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| قواعد تعديل صلاحيات المشرف (T03 — docs/SPEC.md §2 "قواعد إدارة الأدوار")
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->action = app(UpdateSupervisorPermissions::class);
});

function permissionNames(User $user): array
{
    return $user->fresh()->permissions->pluck('name')->sort()->values()->all();
}

test('المدير يضبط صلاحيات مشرف ويُسجَّل التغيير في سجل التدقيق', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view'])->create();

    $this->action->handle($this->admin, $supervisor, ['stats.view', 'transfers.view']);

    expect(permissionNames($supervisor))->toBe(['stats.view', 'transfers.view']);

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('permissions.updated')
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($supervisor->id)
        ->and($log->subject_type)->toBe($supervisor->getMorphClass())
        ->and($log->meta['granted'])->toBe(['stats.view', 'transfers.view'])
        ->and($log->meta['revoked'])->toBe(['users.view']);
});

test('المدير يمنح أي صلاحية بما فيها الحساسة', function (): void {
    $supervisor = User::factory()->supervisor()->create();

    $this->action->handle($this->admin, $supervisor, ['supervisors.manage', 'settings.manage']);

    expect(permissionNames($supervisor))->toBe(['settings.manage', 'supervisors.manage']);
});

test('المشرف لا يعدّل صلاحيات نفسه حتى لو ملك supervisors.manage', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['supervisors.manage', 'users.view'])->create();

    expect(fn () => $this->action->handle($supervisor, $supervisor, ['supervisors.manage']))
        ->toThrow(AuthorizationException::class, 'لا يمكنك تعديل صلاحياتك بنفسك.');

    expect(permissionNames($supervisor))->toBe(['supervisors.manage', 'users.view']);
    expect(AuditLog::query()->count())->toBe(0);
});

test('المشرف لا يمنح غيره صلاحية لا يملكها', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage', 'users.view'])->create();
    $target = User::factory()->supervisor()->create();

    expect(fn () => $this->action->handle($manager, $target, ['users.view', 'users.suspend']))
        ->toThrow(AuthorizationException::class, 'لا يمكنك منح صلاحية لا تملكها: users.suspend');

    expect(permissionNames($target))->toBe([]);
    expect(AuditLog::query()->count())->toBe(0);
});

test('مشرف بصلاحية supervisors.manage لا يمنح صلاحية حساسة حتى لو ملكها', function (string $permission): void {
    $manager = User::factory()->supervisor()
        ->withPermissions(['supervisors.manage', 'recovery.handle', 'recovery.other_number', 'recovery.change_phone', 'beneficiaries.manage', 'settings.manage', 'content.manage'])
        ->create();
    $target = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();

    expect($manager->can($permission))->toBeTrue();

    expect(fn () => $this->action->handle($manager, $target, ['recovery.handle', $permission]))
        ->toThrow(AuthorizationException::class, 'الصلاحيات الحساسة يمنحها المدير وحده: '.$permission);

    expect(permissionNames($target))->toBe(['recovery.handle']);
    expect(AuditLog::query()->count())->toBe(0);
})->with(array_map(fn (PermissionKey $key): string => $key->value, PermissionKey::SENSITIVE_PERMISSIONS));

test('المدير يمنح كل صلاحية حساسة', function (string $permission): void {
    $target = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();

    $this->action->handle($this->admin, $target, ['recovery.handle', $permission]);

    expect(permissionNames($target))->toContain($permission);
})->with(array_map(fn (PermissionKey $key): string => $key->value, PermissionKey::SENSITIVE_PERMISSIONS));

test('مشرف بصلاحية supervisors.manage يُبقي صلاحية حساسة ممنوحة سابقًا ويعدّل غيرها', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage', 'users.view'])->create();
    $target = User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create();

    $this->action->handle($manager, $target, ['beneficiaries.manage', 'users.view']);

    expect(permissionNames($target))->toBe(['beneficiaries.manage', 'users.view']);
});

test('المشرف يمنح غيره صلاحية يملكها', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage', 'users.view'])->create();
    $target = User::factory()->supervisor()->create();

    $this->action->handle($manager, $target, ['users.view']);

    expect(permissionNames($target))->toBe(['users.view']);
    expect(AuditLog::query()->sole()->actor_id)->toBe($manager->id);
});

test('مشرف بلا supervisors.manage لا يعدّل صلاحيات أحد', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view'])->create();
    $target = User::factory()->supervisor()->create();

    expect(fn () => $this->action->handle($supervisor, $target, ['users.view']))
        ->toThrow(AuthorizationException::class, 'لا تملك صلاحية إدارة المشرفين.');
});

test('مدير معطَّل لا يعدّل الصلاحيات', function (): void {
    $inactiveAdmin = User::factory()->admin()->inactive()->create();
    $target = User::factory()->supervisor()->create();

    expect(fn () => $this->action->handle($inactiveAdmin, $target, ['users.view']))
        ->toThrow(AuthorizationException::class);
});

test('مشرف يحاول سحب صلاحيات آخر مدير فيُرفض', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage'])->create();

    expect(fn () => $this->action->handle($manager, $this->admin, []))
        ->toThrow(AuthorizationException::class, 'صلاحيات المدير ثابتة ولا تُعدَّل ولا تُسحب.');

    expect($this->admin->fresh()->can('settings.manage'))->toBeTrue();
    expect(AuditLog::query()->count())->toBe(0);
});

test('المدير لا يسحب صلاحياته بنفسه', function (): void {
    expect(fn () => $this->action->handle($this->admin, $this->admin, []))
        ->toThrow(AuthorizationException::class, 'صلاحيات المدير ثابتة ولا تُعدَّل ولا تُسحب.');

    expect($this->admin->fresh()->can('supervisors.manage'))->toBeTrue();
});

test('لا تُمنح الصلاحيات لمبادر', function (): void {
    $user = User::factory()->create();

    expect(fn () => $this->action->handle($this->admin, $user, ['users.view']))
        ->toThrow(AuthorizationException::class, 'تُمنح الصلاحيات للمشرفين فقط.');
});

test('recovery.other_number وrecovery.change_phone تتطلبان recovery.handle', function (string $permission): void {
    $supervisor = User::factory()->supervisor()->create();

    expect(fn () => $this->action->handle($this->admin, $supervisor, [$permission]))
        ->toThrow(ValidationException::class);

    $this->action->handle($this->admin, $supervisor, ['recovery.handle', $permission]);

    expect(permissionNames($supervisor))->toBe(collect(['recovery.handle', $permission])->sort()->values()->all());
})->with(['recovery.other_number', 'recovery.change_phone']);

test('لا يُسحب recovery.handle مع إبقاء صلاحية تعتمد عليه', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.change_phone'])->create();

    expect(fn () => $this->action->handle($this->admin, $supervisor, ['recovery.change_phone']))
        ->toThrow(ValidationException::class);

    expect(permissionNames($supervisor))->toBe(['recovery.change_phone', 'recovery.handle']);
});

test('مفتاح صلاحية غير معروف يُرفض', function (): void {
    $supervisor = User::factory()->supervisor()->create();

    expect(fn () => $this->action->handle($this->admin, $supervisor, ['users.delete_everything']))
        ->toThrow(ValidationException::class);
});

test('عدم تغيير شيء لا يكتب سجل تدقيق', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view'])->create();

    $this->action->handle($this->admin, $supervisor, ['users.view']);

    expect(AuditLog::query()->count())->toBe(0);
});

test('التغيير يُبطل كاش spatie', function (): void {
    $supervisor = User::factory()->supervisor()->create();
    $registrar = app(PermissionRegistrar::class);

    $registrar->getPermissions();
    expect($registrar->getCacheRepository()->has($registrar->cacheKey))->toBeTrue();

    $this->action->handle($this->admin, $supervisor, ['users.view']);

    expect($registrar->getCacheRepository()->has($registrar->cacheKey))->toBeFalse();
});
