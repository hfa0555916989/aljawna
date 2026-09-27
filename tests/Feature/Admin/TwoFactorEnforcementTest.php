<?php

declare(strict_types=1);

use App\Actions\Admin\InviteAdmin;
use App\Livewire\JoinAdmin;
use App\Models\Page;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| التحقق بخطوتين إلزامي لكل منطقة الإدارة (docs/DECISIONS.md)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

function setUpUrl(): string
{
    return url(adminPath('multi-factor-authentication/set-up'));
}

test('المدير المُنشأ عبر admin:invite يُلزَم بإعداد التحقق عند أول دخول', function (): void {
    $result = app(InviteAdmin::class)->handle('0512345678');

    Livewire::test(JoinAdmin::class, ['token' => $result['plain_token']])
        ->set('full_name', 'عبدالله محمد أحمد العجاوني')
        ->set('password', 'S3cure-pass')
        ->set('password_confirmation', 'S3cure-pass')
        ->call('join')
        ->assertRedirect(url(adminPath()));

    $admin = User::query()->where('phone', '+966512345678')->sole();

    expect($admin->hasTwoFactorEnabled())->toBeFalse();

    $this->get(adminPath())->assertRedirect(setUpUrl());
    $this->get(setUpUrl())->assertOk();
});

test('بعد إعداد التحقق في نفس الجلسة تُفتح اللوحة دون خروج', function (): void {
    $supervisor = User::factory()->supervisor()->withoutTwoFactor()->create();

    $this->actingAs($supervisor);
    $this->get(adminPath())->assertRedirect(setUpUrl());

    AppAuthentication::make()->saveSecret($supervisor, AppAuthentication::make()->generateSecret());

    $this->get(adminPath())->assertOk();
});

test('صفحة الإعداد تعرض إعداد تطبيق المصادقة، وتُحوِّل من أعدّه مسبقًا إلى اللوحة', function (): void {
    $this->actingAs(User::factory()->admin()->withoutTwoFactor()->create())
        ->get(setUpUrl())
        ->assertOk()
        ->assertSee('تطبيق المصادقة');

    $this->actingAs(User::factory()->admin()->create())
        ->get(setUpUrl())
        ->assertRedirect();
});

test('مسارات الإدارة خارج Filament تتطلب إعداد التحقق أيضًا', function (): void {
    $admin = User::factory()->admin()->withoutTwoFactor()->create();
    $page = Page::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.pages.preview', $page))
        ->assertRedirect(setUpUrl());

    $target = User::factory()->supervisor()->create();

    $this->actingAs($admin)
        ->put(route('admin.supervisors.permissions.update', $target), ['permissions' => []])
        ->assertRedirect(setUpUrl());
});

test('مسارات الإدارة خارج Filament تقع تحت ADMIN_PATH', function (): void {
    $page = Page::factory()->create();
    $target = User::factory()->supervisor()->create();

    expect(route('admin.pages.preview', $page, false))->toStartWith(adminPath().'/')
        ->and(route('admin.supervisors.permissions.update', $target, false))->toStartWith(adminPath().'/');
});

test('المبادر لا يُطلب منه إعداد التحقق ولا يصل إلى صفحة الإعداد', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(setUpUrl())
        ->assertNotFound();
});

test('سر التحقق ورموز الاسترداد مخفية ومشفَّرة في قاعدة البيانات', function (): void {
    $admin = User::factory()->admin()->create();
    $provider = AppAuthentication::make()->recoverable();
    $codes = $provider->generateRecoveryCodes();
    $provider->saveRecoveryCodes($admin, $codes);

    $raw = DB::table('users')->where('id', $admin->id)->first();

    expect($admin->toArray())->not->toHaveKeys(['app_authentication_secret', 'app_authentication_recovery_codes'])
        ->and($raw->app_authentication_secret)->not->toBe($admin->getAppAuthenticationSecret())
        ->and((string) $raw->app_authentication_recovery_codes)->not->toContain($codes[0]);
});
