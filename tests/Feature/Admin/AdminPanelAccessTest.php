<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| الوصول إلى لوحة الإدارة /admin ولوحة المعلومات (T04 — docs/SPEC.md §2)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

test('الزائر يُحوَّل إلى دخول اللوحة', function (): void {
    $this->get('/admin')->assertRedirect('/admin/login');
});

test('اللوحة عربية واتجاهها من اليمين لليسار وتحمل هوية المبادرة', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee(config('app.name'))
        ->assertSee('font-brand', false);
});

test('اللوحة لا تحمّل خطوطًا أو صورًا رمزية من خدمات خارجية', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('fonts.bunny.net', false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('ui-avatars.com', false)
        ->assertSee('data:image/svg+xml;base64,', false);
});

test('المدير والمشرف الفعّالان يدخلان اللوحة', function (User $user): void {
    $this->actingAs($user)->get('/admin')->assertOk();
})->with([
    'مدير' => fn () => User::factory()->admin()->create(),
    'مشرف' => fn () => User::factory()->supervisor()->create(),
]);

test('المبادر الفعّال يُحوَّل إلى /dashboard ولا يدخل اللوحة', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertRedirect(route('dashboard'));
});

test('الحساب المعطَّل يُرفض بـ 403 حتى لو كانت جلسته قائمة', function (User $user): void {
    $this->actingAs($user)->get('/admin')->assertForbidden();
})->with([
    'مدير معطَّل' => fn () => User::factory()->admin()->inactive()->create(),
    'مشرف معطَّل' => fn () => User::factory()->supervisor()->inactive()->create(),
    'مبادر معطَّل' => fn () => User::factory()->inactive()->create(),
]);

test('تعطيل المشرف يسري فورًا على الطلب التالي', function (): void {
    User::factory()->admin()->create();
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get('/admin')->assertOk();

    $supervisor->update(['is_active' => false]);

    $this->actingAs($supervisor->fresh())->get('/admin')->assertForbidden();
});

test('مشرف بلا أي صلاحية يرى رسالة "لم يمنحك المدير أي صلاحية بعد"', function (): void {
    $this->actingAs(User::factory()->supervisor()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('لم يمنحك المدير أي صلاحية بعد')
        ->assertDontSee('نظرة عامة');
});

test('رسالة "بلا صلاحية" لا تظهر لمن مُنح صلاحية ولا للمدير', function (User $user): void {
    $this->actingAs($user)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('لم يمنحك المدير أي صلاحية بعد');
})->with([
    'مدير' => fn () => User::factory()->admin()->create(),
    'مشرف بصلاحية واحدة' => fn () => User::factory()->supervisor()->withPermissions(['users.view'])->create(),
]);

test('أداة "نظرة عامة" تظهر للمدير ولمن يملك stats.view فقط', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSee('نظرة عامة');

    $this->actingAs(User::factory()->supervisor()->withPermissions(['stats.view'])->create())
        ->get('/admin')
        ->assertSee('نظرة عامة');

    $this->actingAs(User::factory()->supervisor()->withPermissions(['users.view'])->create())
        ->get('/admin')
        ->assertDontSee('نظرة عامة');
});

test('أداة معلومات Filament الخارجية غير معروضة', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertDontSee('filamentphp.com', false)
        ->assertDontSee('github.com/filamentphp', false);
});
