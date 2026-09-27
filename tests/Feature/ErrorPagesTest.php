<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| صفحات الأخطاء العربية (T20 — tasks/T20-qa-hardening.md #4)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['app.debug' => false]);

    Route::get('/_test/error/{code}', fn (int $code) => abort($code))->whereNumber('code');
});

test('صفحة عربية من اليمين لليسار لكل رمز خطأ', function (int $code, string $title): void {
    $this->get("/_test/error/{$code}")
        ->assertStatus($code)
        ->assertSee('lang="ar"', false)
        ->assertSee('dir="rtl"', false)
        ->assertSee($title)
        ->assertSee(url('/'), false)
        ->assertDontSee('Whoops')
        ->assertDontSee('Symfony');
})->with([
    [403, 'غير مسموح'],
    [404, 'الصفحة غير موجودة'],
    [419, 'انتهت صلاحية الصفحة'],
    [429, 'طلبات كثيرة'],
    [500, 'حدث خطأ غير متوقع'],
    [503, 'الموقع تحت الصيانة'],
]);

test('صفحة غير موجودة من منشئ الصفحات تعرض الصفحة العربية', function (): void {
    $this->get('/no-such-page-here')->assertNotFound()->assertSee('الصفحة غير موجودة');
});

test('صفحة 500 لا تحتاج قاعدة البيانات، ولا تكشف تفاصيل الخطأ', function (): void {
    Route::get('/_test/crash', fn (): never => throw new RuntimeException('secret internal detail 0512345678'));

    DB::enableQueryLog();

    $this->get('/_test/crash')
        ->assertStatus(500)
        ->assertSee('حدث خطأ غير متوقع')
        ->assertDontSee('secret internal detail')
        ->assertDontSee('0512345678');

    expect(DB::getQueryLog())->toBe([]);
});

test('403 داخل لوحة الإدارة عربية أيضًا', function (): void {
    $this->seed(PermissionSeeder::class);

    $this->actingAs(User::factory()->supervisor()->create())
        ->get(adminPath('system-health'))
        ->assertForbidden()
        ->assertSee('غير مسموح');
});
