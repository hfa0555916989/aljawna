<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\Support\TwoFactorSession;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| الدخول الموحّد لأدوار اللوحة عبر /login، والتحقق بخطوتين (docs/DECISIONS.md)
|--------------------------------------------------------------------------
*/

function attemptPanelLogin(string $phone = '0512345678', string $password = 'S3cure-pass'): Testable
{
    return Livewire::test(Login::class)
        ->set('phone', $phone)
        ->set('password', $password)
        ->call('login');
}

function currentTotp(User $user): string
{
    return AppAuthentication::make()->getCurrentCode($user);
}

test('صفحة دخول Filament أُلغيت، ورابطها القديم يُحوَّل إلى /login', function (): void {
    $this->get(adminPath('login'))->assertRedirect('/login');

    expect(class_exists('App\\Filament\\Pages\\Auth\\Login'))->toBeFalse();
});

test('لا يوجد مسار /admin ثابت: الزائر يحصل على 404 عليه ويُعاد إلى /login من مسار اللوحة', function (): void {
    $this->get('/admin')->assertNotFound();
    $this->get(adminPath())->assertRedirect(route('login'));
});

test('دور لوحة فعّل التحقق يُطلب منه الرمز بعد كلمة المرور، ولا يُسجَّل دخوله قبله', function (string $role): void {
    User::factory()->{$role}()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptPanelLogin()
        ->assertHasNoErrors()
        ->assertNoRedirect()
        ->assertSet('password', '')
        ->assertNotSet('challengedUser', null)
        ->assertSee('التحقق بخطوتين')
        ->assertSee('autocomplete="one-time-code"', false);

    expect(Auth::check())->toBeFalse()
        ->and(LoginAttempt::query()->count())->toBe(0);
})->with(['admin', 'supervisor']);

test('الرمز الصحيح يُكمل الدخول إلى اللوحة ويسجّل المحاولة الناجحة', function (string $role): void {
    $user = User::factory()->{$role}()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptPanelLogin()
        ->set('code', currentTotp($user))
        ->call('verifyTwoFactor')
        ->assertHasNoErrors()
        ->assertRedirect(url(adminPath()));

    expect(Auth::id())->toBe($user->id)
        ->and(session(TwoFactorSession::SESSION_KEY))->toBeTrue()
        ->and(LoginAttempt::query()->sole()->succeeded)->toBeTrue()
        ->and($user->refresh()->last_login_at)->not->toBeNull();

    $this->get(adminPath())->assertOk();
})->with(['admin', 'supervisor']);

test('الرمز الخاطئ يُرفض ويُحتسب محاولة فاشلة، وبعد 5 يُقفل الدخول حتى بالرمز الصحيح', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    $component = attemptPanelLogin();

    foreach (range(1, 5) as $ignored) {
        $component->set('code', '000000')->call('verifyTwoFactor')->assertHasErrors(['code']);
    }

    $component->set('code', currentTotp($admin))
        ->call('verifyTwoFactor')
        ->assertHasErrors(['code'])
        ->assertSee('أُوقف الدخول مؤقتًا');

    expect(Auth::check())->toBeFalse()
        ->and(LoginAttempt::query()->where('succeeded', false)->count())->toBe(6)
        ->and(LoginAttempt::query()->where('succeeded', true)->count())->toBe(0);
});

test('الرمز نفسه لا يُقبل مرتين', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);
    $code = currentTotp($admin);

    attemptPanelLogin()->set('code', $code)->call('verifyTwoFactor')->assertHasNoErrors();

    Auth::logout();

    attemptPanelLogin()->set('code', $code)->call('verifyTwoFactor')->assertHasErrors(['code']);

    expect(Auth::check())->toBeFalse();
});

test('رمز الاسترداد يُدخل مرة واحدة فقط ويُسجَّل في التدقيق', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);
    $provider = AppAuthentication::make()->recoverable();
    $codes = $provider->generateRecoveryCodes();
    $provider->saveRecoveryCodes($admin, $codes);

    attemptPanelLogin()
        ->call('toggleRecoveryCode')
        ->assertSee('رمز الاسترداد')
        ->set('recoveryCode', $codes[0])
        ->call('verifyTwoFactor')
        ->assertHasNoErrors()
        ->assertRedirect(url(adminPath()));

    expect(Auth::id())->toBe($admin->id)
        ->and($admin->refresh()->getAppAuthenticationRecoveryCodes())->toHaveCount(count($codes) - 1);

    $audit = AuditLog::query()->where('action', Login::RECOVERY_CODE_AUDIT_ACTION)->sole();
    expect($audit->subject_id)->toBe($admin->id)
        ->and($audit->meta)->toBe(['remaining' => count($codes) - 1]);

    Auth::logout();

    attemptPanelLogin()
        ->call('toggleRecoveryCode')
        ->set('recoveryCode', $codes[0])
        ->call('verifyTwoFactor')
        ->assertHasErrors(['recoveryCode']);

    expect(Auth::check())->toBeFalse();
});

test('مهلة إدخال الرمز تنتهي بعد 10 دقائق فيبدأ الدخول من جديد', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    $component = attemptPanelLogin();

    $this->travel(Login::CHALLENGE_SECONDS + 1)->seconds();

    $component->set('code', currentTotp($admin))
        ->call('verifyTwoFactor')
        ->assertSet('challengedUser', null)
        ->assertHasErrors(['phone'])
        ->assertSee('انتهت مهلة إدخال الرمز');

    expect(Auth::check())->toBeFalse();
});

test('لا يمكن تزوير صاحب الخطوة الثانية دون كلمة المرور', function (): void {
    User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    expect(fn () => Livewire::test(Login::class)->set('challengedUser', 'forged'))
        ->toThrow(Exception::class);

    expect(Auth::check())->toBeFalse();
});

test('دور لوحة لم يُعدّ التحقق بعد لا يدخل بكلمة المرور وحدها ولا تُنشأ له جلسة (T20)', function (string $role): void {
    User::factory()->{$role}()->withoutTwoFactor()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptPanelLogin()
        ->assertHasErrors(['phone'])
        ->assertNoRedirect()
        ->assertSet('challengedUser', null)
        ->assertSee('لا يمكن دخول هذا الحساب قبل إعداد التحقق بخطوتين');

    expect(Auth::check())->toBeFalse()
        ->and(LoginAttempt::query()->sole()->succeeded)->toBeFalse();

    $this->get(adminPath())->assertRedirect(route('login'));
})->with(['admin', 'supervisor']);

test('كلمة المرور الخاطئة لدور لوحة بلا تحقق تبقى "بيانات الدخول غير صحيحة" ولا تكشف حالة التحقق', function (): void {
    User::factory()->admin()->withoutTwoFactor()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptPanelLogin(password: 'wrong-pass')
        ->assertHasErrors(['phone'])
        ->assertSee('بيانات الدخول غير صحيحة')
        ->assertDontSee('إعداد التحقق بخطوتين');
});

test('المبادر لا تُطلب منه خطوة ثانية ويذهب إلى /dashboard', function (): void {
    User::factory()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptPanelLogin()->assertHasNoErrors()->assertRedirect(route('dashboard'));
});

test('المبادر الذي طلب مسار اللوحة قبل الدخول لا يُعاد إليه بعده', function (): void {
    User::factory()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    $this->get(adminPath('security'))->assertRedirect(route('login'));

    attemptPanelLogin()->assertRedirect(route('dashboard'));
});

test('دور اللوحة يُعاد إلى صفحة اللوحة التي طلبها قبل الدخول', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    $this->get(adminPath('security'))->assertRedirect(route('login'));

    attemptPanelLogin()
        ->set('code', currentTotp($admin))
        ->call('verifyTwoFactor')
        ->assertRedirect(url(adminPath('security')));
});

test('جلسة دخلت دون اجتياز الخطوة الثانية تُرفض في اللوحة ويُعاد صاحبها إلى /login', function (): void {
    $admin = User::factory()->admin()->create();

    Auth::login($admin);

    expect(session(TwoFactorSession::SESSION_KEY))->toBeFalse();

    $this->get(adminPath())->assertRedirect(route('login'));
    $this->assertGuest();
});

test('الحساب المعطَّل للمشرف أو المدير يُمنع برسالة التواصل مع الإدارة قبل الخطوة الثانية', function (string $role): void {
    User::factory()->{$role}()->inactive()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptPanelLogin()
        ->assertHasErrors(['phone'])
        ->assertSet('challengedUser', null)
        ->assertSee('حسابك معطَّل. تواصل مع إدارة المبادرة.');

    expect(Auth::check())->toBeFalse();
})->with(['admin', 'supervisor']);

test('تعطيل الحساب أثناء الخطوة الثانية يمنع إكمال الدخول', function (): void {
    User::factory()->admin()->create();
    $supervisor = User::factory()->supervisor()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    $component = attemptPanelLogin();

    $supervisor->update(['is_active' => false]);

    $component->set('code', currentTotp($supervisor))
        ->call('verifyTwoFactor')
        ->assertHasErrors(['code'])
        ->assertSee('حسابك معطَّل');

    expect(Auth::check())->toBeFalse();
});

test('بيانات خاطئة لدور لوحة تُرفض برسالة موحّدة دون الوصول إلى الخطوة الثانية', function (string $phone, string $password): void {
    User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptPanelLogin($phone, $password)
        ->assertHasErrors(['phone'])
        ->assertSee('بيانات الدخول غير صحيحة')
        ->assertSet('challengedUser', null);

    expect(Auth::check())->toBeFalse();
})->with([
    'كلمة مرور خاطئة' => ['0512345678', 'wrong-pass'],
    'رقم غير مسجّل' => ['0598765432', 'S3cure-pass'],
    'رقم غير سعودي' => ['+201001234567', 'S3cure-pass'],
]);

test('مسار اللوحة يُقرأ من ADMIN_PATH، والقيمة الفارغة تعود إلى الافتراضي غير المتوقع', function (): void {
    $load = function (string $value): string {
        $_SERVER['ADMIN_PATH'] = $_ENV['ADMIN_PATH'] = $value;

        try {
            return (require config_path('admin.php'))['path'];
        } finally {
            unset($_SERVER['ADMIN_PATH'], $_ENV['ADMIN_PATH']);
        }
    };

    expect($load('/my-secret-panel/'))->toBe('my-secret-panel')
        ->and($load(''))->toBe('hq-7r3m9k')
        ->and($load(''))->not->toBe('admin');
});
