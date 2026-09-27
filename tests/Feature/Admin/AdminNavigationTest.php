<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Tests\Concerns\RegistersPermissionFixturePages;

/*
|--------------------------------------------------------------------------
| التنقل حسب الصلاحية و403 للوصول المباشر (T04 — FR-18)
|--------------------------------------------------------------------------
| صفحتا اختبار ترثان App\Filament\Pages\PermissionPage كما سترثها صفحات
| المهام اللاحقة.
*/

uses(RegistersPermissionFixturePages::class);

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

const SECURITY_LABEL = 'صفحة اختبار الأمان';
const OTHER_NUMBER_LABEL = 'صفحة اختبار الرقم المختلف';

test('المدير يرى كل عناصر التنقل ويفتح صفحاتها', function (): void {
    $this->actingAs(User::factory()->admin()->create());

    $this->get(adminPath())
        ->assertOk()
        ->assertSee(SECURITY_LABEL)
        ->assertSee(OTHER_NUMBER_LABEL);

    $this->get(adminPath('fixture-security'))->assertOk();
    $this->get(adminPath('fixture-other-number'))->assertOk();
});

test('المشرف يرى عنصر ما مُنح فقط', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['security.view'])->create())
        ->get(adminPath())
        ->assertOk()
        ->assertSee(SECURITY_LABEL)
        ->assertDontSee(OTHER_NUMBER_LABEL);
});

test('مشرف بلا صلاحية لا يرى أي عنصر محمي', function (): void {
    $this->actingAs(User::factory()->supervisor()->create())
        ->get(adminPath())
        ->assertOk()
        ->assertDontSee(SECURITY_LABEL)
        ->assertDontSee(OTHER_NUMBER_LABEL);
});

test('الوصول المباشر بالرابط لصفحة غير ممنوحة يُرفض بـ 403', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['users.view'])->create())
        ->get(adminPath('fixture-security'))
        ->assertForbidden();
});

test('المشرف يفتح الصفحة الممنوحة مباشرة بالرابط', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['security.view'])->create())
        ->get(adminPath('fixture-security'))
        ->assertOk();
});

test('صلاحية بلا اعتماديتها لا تُظهر العنصر وتُرفض بـ 403', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['recovery.other_number'])->create());

    $this->get(adminPath())->assertDontSee(OTHER_NUMBER_LABEL);
    $this->get(adminPath('fixture-other-number'))->assertForbidden();
});

test('صلاحية مع اعتماديتها تُظهر العنصر وتفتح الصفحة', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.other_number'])->create());

    $this->get(adminPath())->assertSee(OTHER_NUMBER_LABEL);
    $this->get(adminPath('fixture-other-number'))->assertOk();
});

test('سحب الصلاحية يسري فورًا على الطلب التالي بالرابط المباشر', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['security.view'])->create();

    $this->actingAs($supervisor)->get(adminPath('fixture-security'))->assertOk();

    $supervisor->revokePermissionTo('security.view');

    $this->actingAs($supervisor->fresh())->get(adminPath('fixture-security'))->assertForbidden();
});

test('المبادر لا يصل إلى الصفحة المحمية (404)', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(adminPath('fixture-security'))
        ->assertNotFound();
});

test('الزائر يُحوَّل إلى الدخول عند فتح صفحة محمية', function (): void {
    $this->get(adminPath('fixture-security'))->assertRedirect(route('login'));
});
