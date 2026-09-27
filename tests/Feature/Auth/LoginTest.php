<?php

declare(strict_types=1);

use App\Livewire\Auth\Login;
use App\Models\LoginAttempt;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| الدخول والقفل المؤقت (T02 — docs/SPEC.md §3, FR-5, §12.1, §12.2)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'phone' => '+966512345678',
        'password' => 'S3cure-pass',
    ]);
});

function attemptLogin(string $phone, string $password): Testable
{
    return Livewire::test(Login::class)
        ->set('phone', $phone)
        ->set('password', $password)
        ->call('login');
}

/**
 * دور لوحة بتحقق مفعَّل: دخول بكلمة المرور ثم رمز TOTP الحالي.
 */
function attemptLoginWithTwoFactor(User $user, string $role): Testable
{
    $provider = AppAuthentication::make();
    $user->forceFill(['role' => $role, 'app_authentication_secret' => $provider->generateSecret()])->save();

    return attemptLogin('0512345678', 'S3cure-pass')
        ->set('code', $provider->getCurrentCode($user))
        ->call('verifyTwoFactor');
}

test('صفحة الدخول تُعرض بحقول معنونة', function (): void {
    $this->get('/login')
        ->assertOk()
        ->assertSeeLivewire(Login::class)
        ->assertSee('for="phone"', false)
        ->assertSee('for="password"', false)
        ->assertDontSee('type="email"', false);
});

test('الدخول الناجح يوجّه إلى اللوحة ويحدّث بيانات آخر دخول ويسجّل المحاولة', function (): void {
    attemptLogin('0512345678', 'S3cure-pass')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    expect(Auth::id())->toBe($this->user->id);

    $this->user->refresh();
    expect($this->user->last_login_ip)->toBe('127.0.0.1')
        ->and($this->user->last_login_at)->not->toBeNull();

    $attempt = LoginAttempt::query()->sole();
    expect($attempt->phone)->toBe('+966512345678')
        ->and($attempt->ip)->toBe('127.0.0.1')
        ->and($attempt->succeeded)->toBeTrue();
});

test('المشرف والمدير قبل إعداد التحقق بخطوتين لا يدخلان بكلمة المرور وحدها (T20)', function (string $role): void {
    $this->user->update(['role' => $role]);

    attemptLogin('0512345678', 'S3cure-pass')
        ->assertHasErrors(['phone'])
        ->assertNoRedirect()
        ->assertSee('لا يمكن دخول هذا الحساب قبل إعداد التحقق بخطوتين');

    expect(Auth::check())->toBeFalse()
        ->and(LoginAttempt::query()->sole()->succeeded)->toBeFalse()
        ->and($this->user->fresh()->last_login_at)->toBeNull();
})->with(['supervisor', 'admin']);

test('المشرف يُحوَّل إلى اللوحة حتى لو سبق أن طلب صفحة أخرى', function (): void {
    $this->get('/dashboard')->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(url('/dashboard'));

    attemptLoginWithTwoFactor($this->user, 'supervisor')->assertRedirect(url(adminPath()));
});

test('الدخول يقبل الجوال بالأرقام العربية', function (): void {
    attemptLogin('٠٥١٢٣٤٥٦٧٨', 'S3cure-pass')->assertHasNoErrors();

    expect(Auth::id())->toBe($this->user->id);
});

test('الدخول يجدّد معرّف الجلسة', function (): void {
    Session::start();
    $sessionIdBefore = Session::getId();

    attemptLogin('0512345678', 'S3cure-pass')->assertHasNoErrors();

    expect(Session::getId())->not->toBe($sessionIdBefore);
});

test('رسالة خطأ موحّدة لكلمة مرور خاطئة أو رقم غير مسجّل أو صيغة غير صحيحة', function (string $phone, string $password): void {
    attemptLogin($phone, $password)
        ->assertHasErrors(['phone'])
        ->assertSee('بيانات الدخول غير صحيحة')
        ->assertSet('password', '');

    expect(Auth::check())->toBeFalse();
    expect(LoginAttempt::query()->where('succeeded', false)->count())->toBe(1);
})->with([
    'كلمة مرور خاطئة' => ['0512345678', 'wrong-pass'],
    'رقم غير مسجّل' => ['0598765432', 'S3cure-pass'],
    'صيغة غير سعودية' => ['+971501234567', 'S3cure-pass'],
]);

test('الحساب المعطَّل يُمنع من الدخول برسالة التواصل مع الإدارة', function (): void {
    $this->user->update(['is_active' => false]);

    attemptLogin('0512345678', 'S3cure-pass')
        ->assertHasErrors(['phone'])
        ->assertSee('حسابك معطَّل. تواصل مع إدارة المبادرة.');

    expect(Auth::check())->toBeFalse();
});

test('بعد 5 محاولات فاشلة يُقفل الدخول حتى بكلمة المرور الصحيحة، وتُسجَّل كل المحاولات', function (): void {
    foreach (range(1, 5) as $ignored) {
        attemptLogin('0512345678', 'wrong-pass')->assertSee('بيانات الدخول غير صحيحة');
    }

    attemptLogin('0512345678', 'S3cure-pass')
        ->assertHasErrors(['phone'])
        ->assertSee('أُوقف الدخول مؤقتًا');

    expect(Auth::check())->toBeFalse();
    expect(LoginAttempt::query()->count())->toBe(6)
        ->and(LoginAttempt::query()->where('succeeded', true)->count())->toBe(0);
});

test('4 محاولات فاشلة لا تقفل الدخول', function (): void {
    foreach (range(1, 4) as $ignored) {
        attemptLogin('0512345678', 'wrong-pass');
    }

    attemptLogin('0512345678', 'S3cure-pass')->assertHasNoErrors();

    expect(Auth::id())->toBe($this->user->id);
});

test('ينتهي القفل بعد مدته فيُسمح بالدخول', function (): void {
    foreach (range(1, 5) as $ignored) {
        attemptLogin('0512345678', 'wrong-pass');
    }

    $this->travel(16)->minutes();

    attemptLogin('0512345678', 'S3cure-pass')->assertHasNoErrors();

    expect(Auth::id())->toBe($this->user->id);
});

test('الزائر يُحوَّل من اللوحة إلى صفحة الدخول', function (): void {
    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('المستخدم المسجّل دخوله يُحوَّل من صفحة الدخول إلى اللوحة', function (): void {
    $this->actingAs($this->user)
        ->get('/login')
        ->assertRedirect(route('dashboard'));
});

test('المشرف والمدير بجلسة قائمة يُحوَّلان من /login إلى اللوحة', function (string $role): void {
    $this->user->forceFill(['role' => $role, 'app_authentication_secret' => AppAuthentication::make()->generateSecret()])->save();

    $this->actingAs($this->user)
        ->get('/login')
        ->assertRedirect(url(adminPath()));
})->with(['supervisor', 'admin']);

test('وجهة زيارة /login بجلسة قائمة هي نفسها وجهة إرسال النموذج لكل دور', function (string $role, string $destination): void {
    $destination = $destination === 'panel' ? adminPath() : $destination;

    ($role === 'user' ? attemptLogin('0512345678', 'S3cure-pass') : attemptLoginWithTwoFactor($this->user, $role))
        ->assertRedirect(url($destination));

    $this->get('/login')->assertRedirect(url($destination));
})->with([
    'مبادر' => ['user', '/dashboard'],
    'مشرف' => ['supervisor', 'panel'],
    'مدير' => ['admin', 'panel'],
]);

test('كوكيز الجلسة HttpOnly وSecure وSameSite افتراضيًا', function (): void {
    expect(config('session.http_only'))->toBeTrue()
        ->and(config('session.secure'))->toBeTrue()
        ->and(config('session.same_site'))->toBe('lax');
});
