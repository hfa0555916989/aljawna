<?php

declare(strict_types=1);

use App\Models\User;
use App\UserRole;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/*
|--------------------------------------------------------------------------
| اختبار بنية جدول المستخدمين (T01)
|--------------------------------------------------------------------------
| docs/SPEC.md §9 users، ومعايير قبول tasks/T01-users-base.md.
*/

test('جدول المستخدمين يحتوي الأعمدة المطلوبة فقط دون بريد', function (): void {
    $expectedColumns = [
        'id', 'full_name', 'phone', 'password', 'role', 'is_active',
        'show_contact', 'registered_ip', 'last_login_ip', 'last_login_at',
        'remember_token', 'created_at', 'updated_at',
    ];

    foreach ($expectedColumns as $column) {
        expect(Schema::hasColumn('users', $column))->toBeTrue("العمود [{$column}] غير موجود في جدول users.");
    }

    expect(Schema::hasColumn('users', 'email'))->toBeFalse();
    expect(Schema::hasColumn('users', 'email_verified_at'))->toBeFalse();
});

test('نموذج المستخدم لا يسمح بتعبئة جماعية للبريد ويخفي كلمة المرور', function (): void {
    $fillable = (new User)->getFillable();

    expect($fillable)->not->toContain('email');
    expect((new User)->getHidden())->toContain('password')->toContain('remember_token');
});

test('رقم الجوال فريد ولا يقبل التكرار', function (): void {
    User::factory()->create(['phone' => '+966512345678']);

    expect(fn () => User::factory()->create(['phone' => '+966512345678']))
        ->toThrow(QueryException::class);
});

test('الدور الافتراضي مبادر (user) ويُحفظ كِـ UserRole', function (): void {
    $user = User::factory()->create();

    expect($user->role)->toBe(UserRole::User);

    $supervisor = User::factory()->supervisor()->create();
    expect($supervisor->role)->toBe(UserRole::Supervisor);

    $admin = User::factory()->admin()->create();
    expect($admin->role)->toBe(UserRole::Admin);
});

test('الحساب المعطَّل is_active يُخزَّن ويُقرأ كقيمة منطقية', function (): void {
    $user = User::factory()->inactive()->create();

    expect($user->is_active)->toBeFalse();
    expect($user->refresh()->is_active)->toBeFalse();
});
