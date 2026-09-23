<?php

declare(strict_types=1);

use App\Actions\Supervisors\DemoteAdmin;
use App\Models\AuditLog;
use App\Models\User;
use App\PermissionKey;
use App\UserRole;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;

/*
|--------------------------------------------------------------------------
| تخفيض المدير: لمدير فعّال آخر فقط (T03 — قرار مراجعة PR #5)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->otherAdmin = User::factory()->admin()->create();
    $this->action = app(DemoteAdmin::class);
});

test('مدير يخفّض مديرًا آخر ويُسجَّل الإجراء', function (): void {
    $this->action->handle($this->admin, $this->otherAdmin);

    expect($this->otherAdmin->fresh()->role)->toBe(UserRole::Supervisor)
        ->and($this->otherAdmin->fresh()->can('settings.manage'))->toBeFalse();

    $log = AuditLog::query()->sole();
    expect($log->action)->toBe('admin.demoted')
        ->and($log->actor_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($this->otherAdmin->id)
        ->and($log->meta)->toBe(['from' => 'admin', 'to' => 'supervisor']);
});

test('مشرف يملك كل الصلاحيات لا يخفّض مديرًا', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(PermissionKey::values())->create();

    expect(fn () => $this->action->handle($supervisor, $this->otherAdmin))
        ->toThrow(AuthorizationException::class, 'تخفيض المدير من حق مدير آخر فقط.');

    expect($this->otherAdmin->fresh()->role)->toBe(UserRole::Admin);
    expect(AuditLog::query()->count())->toBe(0);
});

test('المدير لا يخفّض نفسه', function (): void {
    expect(fn () => $this->action->handle($this->admin, $this->admin))
        ->toThrow(AuthorizationException::class, 'لا يمكنك تخفيض دورك بنفسك.');

    expect($this->admin->fresh()->role)->toBe(UserRole::Admin);
});

test('مدير معطَّل لا يخفّض مديرًا', function (): void {
    $inactiveAdmin = User::factory()->admin()->inactive()->create();

    expect(fn () => $this->action->handle($inactiveAdmin, $this->otherAdmin))
        ->toThrow(AuthorizationException::class);

    expect($this->otherAdmin->fresh()->role)->toBe(UserRole::Admin);
});

test('لا يُطبَّق التخفيض على غير المدير', function (): void {
    $supervisor = User::factory()->supervisor()->create();

    expect(fn () => $this->action->handle($this->admin, $supervisor, UserRole::User))
        ->toThrow(AuthorizationException::class, 'هذا الحساب ليس مديرًا.');
});

test('التخفيض إلى مبادر مسموح', function (): void {
    $this->action->handle($this->admin, $this->otherAdmin, UserRole::User);

    expect($this->otherAdmin->fresh()->role)->toBe(UserRole::User);
});

test('الدور الجديد لا يكون مديرًا', function (): void {
    $this->action->handle($this->admin, $this->otherAdmin, UserRole::Admin);
})->throws(InvalidArgumentException::class);

test('بعد التخفيض لا يستطيع المدير الأخير أن يُخفَّض', function (): void {
    $this->action->handle($this->admin, $this->otherAdmin);

    expect(fn () => $this->action->handle($this->otherAdmin->fresh(), $this->admin))
        ->toThrow(AuthorizationException::class, 'تخفيض المدير من حق مدير آخر فقط.');

    expect($this->admin->fresh()->role)->toBe(UserRole::Admin);
});
