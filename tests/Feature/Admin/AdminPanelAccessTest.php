<?php

declare(strict_types=1);

use App\Models\SiteBranding;
use App\Models\User;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| الوصول إلى لوحة الإدارة (ADMIN_PATH) ولوحة المعلومات (T04 — docs/SPEC.md §2)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

test('الزائر يُحوَّل إلى صفحة الدخول الموحّدة /login', function (): void {
    $this->get(adminPath())->assertRedirect(route('login'));
});

test('اللوحة عربية واتجاهها من اليمين لليسار وتحمل هوية المبادرة (T16 — الشعار المعتمد)', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath())
        ->assertOk()
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee(SiteBranding::current()->initiative_name)
        ->assertSee('src="'.route('brand.asset', ['asset' => SiteBranding::DEFAULT_LIGHT_LOGO_ASSET]).'"', false);
});

test('اللوحة لا تحمّل خطوطًا أو صورًا رمزية من خدمات خارجية', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath())
        ->assertOk()
        ->assertDontSee('fonts.bunny.net', false)
        ->assertDontSee('fonts.googleapis.com', false)
        ->assertDontSee('ui-avatars.com', false)
        ->assertSee('data:image/svg+xml;base64,', false);
});

test('المدير والمشرف الفعّالان يدخلان اللوحة', function (User $user): void {
    $this->actingAs($user)->get(adminPath())->assertOk();
})->with([
    'مدير' => fn () => User::factory()->admin()->create(),
    'مشرف' => fn () => User::factory()->supervisor()->create(),
]);

test('المبادر الفعّال يحصل على 404 لا 403، فلا تكشف اللوحة وجودها', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(adminPath())
        ->assertNotFound();
});

test('الحساب المعطَّل يحصل على 404 حتى لو كانت جلسته قائمة', function (User $user): void {
    $this->actingAs($user)->get(adminPath())->assertNotFound();
})->with([
    'مدير معطَّل' => fn () => User::factory()->admin()->inactive()->create(),
    'مشرف معطَّل' => fn () => User::factory()->supervisor()->inactive()->create(),
    'مبادر معطَّل' => fn () => User::factory()->inactive()->create(),
]);

test('تعطيل المشرف يسري فورًا على الطلب التالي', function (): void {
    User::factory()->admin()->create();
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get(adminPath())->assertOk();

    $supervisor->update(['is_active' => false]);

    $this->actingAs($supervisor->fresh())->get(adminPath())->assertNotFound();
});

test('مشرف بلا أي صلاحية يرى رسالة "لم يمنحك المدير أي صلاحية بعد"', function (): void {
    $this->actingAs(User::factory()->supervisor()->create())
        ->get(adminPath())
        ->assertOk()
        ->assertSee('لم يمنحك المدير أي صلاحية بعد')
        ->assertDontSee('نظرة عامة');
});

test('رسالة "بلا صلاحية" لا تظهر لمن مُنح صلاحية ولا للمدير', function (User $user): void {
    $this->actingAs($user)
        ->get(adminPath())
        ->assertOk()
        ->assertDontSee('لم يمنحك المدير أي صلاحية بعد');
})->with([
    'مدير' => fn () => User::factory()->admin()->create(),
    'مشرف بصلاحية واحدة' => fn () => User::factory()->supervisor()->withPermissions(['users.view'])->create(),
]);

test('أداة "نظرة عامة" تظهر للمدير ولمن يملك stats.view فقط', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath())
        ->assertSee('نظرة عامة');

    $this->actingAs(User::factory()->supervisor()->withPermissions(['stats.view'])->create())
        ->get(adminPath())
        ->assertSee('نظرة عامة');

    $this->actingAs(User::factory()->supervisor()->withPermissions(['users.view'])->create())
        ->get(adminPath())
        ->assertDontSee('نظرة عامة');
});

test('أداة معلومات Filament الخارجية غير معروضة', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath())
        ->assertDontSee('filamentphp.com', false)
        ->assertDontSee('github.com/filamentphp', false);
});
