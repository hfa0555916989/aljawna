<?php

declare(strict_types=1);

use App\Models\User;
use App\UserRole;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

/*
|--------------------------------------------------------------------------
| اختبار Seeder المدير الأول (T01)
|--------------------------------------------------------------------------
| يجب أن يعمل أكثر من مرة دون إنشاء مديرين مكررين، وأن يقرأ بيانات المدير من
| الإعدادات (.env) لا من قيم ثابتة في الكود.
*/

test('ينشئ مديرًا واحدًا من إعدادات البيئة', function (): void {
    $this->seed(AdminUserSeeder::class);

    $admin = User::query()->where('phone', config('admin.phone'))->first();

    expect($admin)->not->toBeNull();
    expect($admin->role)->toBe(UserRole::Admin);
    expect($admin->is_active)->toBeTrue();
    expect($admin->full_name)->toBe(config('admin.name'));
    expect(Hash::check((string) config('admin.password'), $admin->password))->toBeTrue();
});

test('تشغيل Seeder مرتين لا يُنشئ مديرين مكررين', function (): void {
    $this->seed(AdminUserSeeder::class);
    $this->seed(AdminUserSeeder::class);

    $count = User::query()->where('phone', config('admin.phone'))->count();

    expect($count)->toBe(1);
});

test('لا ينشئ أي حساب إن كانت إعدادات المدير غير مكتملة', function (): void {
    config(['admin.phone' => null]);

    $this->seed(AdminUserSeeder::class);

    expect(User::query()->count())->toBe(0);
});
