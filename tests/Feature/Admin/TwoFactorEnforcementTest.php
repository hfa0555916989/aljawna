<?php

declare(strict_types=1);

use App\Actions\Admin\InviteAdmin;
use App\Livewire\JoinAdmin;
use App\Models\Page;
use App\Models\User;
use App\Support\TwoFactorSession;
use Database\Seeders\PermissionSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| التحقق بخطوتين إلزامي قبل أي وصول لأدوار اللوحة (docs/DECISIONS.md، T20)
|--------------------------------------------------------------------------
| الإعداد لا يكون داخل اللوحة، بل من رابط الدعوة أو رابط admin:reset-2fa.
| أي جلسة دور لوحة بلا تحقق مفعَّل أو لم تجتز خطوته الثانية تُنهى في كل مسار.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

test('المدير المُنشأ عبر admin:invite يُعدّ التحقق قبل إنشاء حسابه ويدخل اللوحة فورًا', function (): void {
    $result = app(InviteAdmin::class)->handle('0512345678');

    Livewire::test(JoinAdmin::class, ['token' => $result['plain_token']])
        ->set('full_name', 'عبدالله محمد أحمد العجاوني')
        ->set('password', 'S3cure-pass')
        ->set('password_confirmation', 'S3cure-pass')
        ->set('two_factor_code', pendingTwoFactorCode('join:admin:'.$result['plain_token']))
        ->call('join')
        ->assertHasNoErrors();

    $admin = User::query()->where('phone', '+966512345678')->sole();

    expect($admin->hasTwoFactorEnabled())->toBeTrue()
        ->and(session(TwoFactorSession::SESSION_KEY))->toBeTrue();

    $this->get(adminPath())->assertOk();
});

test('جلسة دور لوحة بلا تحقق مفعَّل تُنهى في اللوحة وفي مسارات الموقع', function (string $path): void {
    $supervisor = User::factory()->supervisor()->withoutTwoFactor()->create();

    $this->actingAs($supervisor)->get($path === 'panel' ? adminPath() : $path)->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
})->with([
    'اللوحة' => ['panel'],
    'لوحة المبادر' => ['/dashboard'],
    'حوالاتي' => ['/my-transfers'],
    'الرئيسية' => ['/'],
]);

test('جلسة صاحب تحقق مفعَّل لم تجتز الخطوة الثانية تُنهى في مسارات الموقع أيضًا', function (): void {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->withSession([TwoFactorSession::SESSION_KEY => false])
        ->get('/dashboard')
        ->assertRedirect(route('login'));

    expect(Auth::check())->toBeFalse();
});

test('لا صفحة إعداد للتحقق داخل اللوحة', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath('multi-factor-authentication/set-up'))
        ->assertNotFound();
});

test('مسارات الإدارة خارج Filament تُنهي جلسة دور لوحة بلا تحقق أيضًا', function (): void {
    $admin = User::factory()->admin()->withoutTwoFactor()->create();
    $page = Page::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.pages.preview', $page))
        ->assertRedirect(route('login'));

    $target = User::factory()->supervisor()->create();

    $this->actingAs($admin)
        ->put(route('admin.supervisors.permissions.update', $target), ['permissions' => []])
        ->assertRedirect(route('login'));

    expect($target->fresh()->permissions()->count())->toBe(0);
});

test('مسارات الإدارة خارج Filament تقع تحت ADMIN_PATH', function (): void {
    $page = Page::factory()->create();
    $target = User::factory()->supervisor()->create();

    expect(route('admin.pages.preview', $page, false))->toStartWith(adminPath().'/')
        ->and(route('admin.supervisors.permissions.update', $target, false))->toStartWith(adminPath().'/');
});

test('المبادر لا يُطلب منه التحقق ويصل إلى لوحته، ولا تكشف اللوحة وجودها له', function (): void {
    $initiator = User::factory()->create();

    $this->actingAs($initiator)->get('/dashboard')->assertOk();
    $this->actingAs($initiator)->get(adminPath('multi-factor-authentication/set-up'))->assertNotFound();
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
