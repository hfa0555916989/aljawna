<?php

declare(strict_types=1);

use App\Models\User;
use App\UserRole;
use Illuminate\Auth\Access\AuthorizationException;

/*
|--------------------------------------------------------------------------
| يبقى مدير فعّال واحد على الأقل (T03 — docs/SPEC.md §2)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

test('لا يمكن سحب دور المدير من آخر مدير', function (): void {
    expect(fn () => $this->admin->update(['role' => UserRole::Supervisor]))
        ->toThrow(AuthorizationException::class, 'يجب أن يبقى مدير فعّال واحد على الأقل.');

    expect($this->admin->fresh()->role)->toBe(UserRole::Admin);
});

test('لا يمكن تعطيل آخر مدير', function (): void {
    expect(fn () => $this->admin->update(['is_active' => false]))
        ->toThrow(AuthorizationException::class);

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

test('لا يمكن حذف آخر مدير', function (): void {
    expect(fn () => $this->admin->delete())->toThrow(AuthorizationException::class);

    expect(User::query()->whereKey($this->admin->id)->exists())->toBeTrue();
});

test('المدير المعطَّل لا يُحتسب مديرًا باقيًا', function (): void {
    User::factory()->admin()->inactive()->create();

    expect(fn () => $this->admin->update(['role' => UserRole::Supervisor]))
        ->toThrow(AuthorizationException::class);
});

test('مع وجود مدير فعّال آخر يُسمح بتغيير دور أحدهما', function (): void {
    User::factory()->admin()->create();

    $this->admin->update(['role' => UserRole::Supervisor]);

    expect($this->admin->fresh()->role)->toBe(UserRole::Supervisor);
});

test('تعديل بيانات أخرى لآخر مدير مسموح', function (): void {
    $this->admin->update(['last_login_ip' => '127.0.0.1']);

    expect($this->admin->fresh()->last_login_ip)->toBe('127.0.0.1');
});
