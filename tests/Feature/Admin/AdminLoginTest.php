<?php

declare(strict_types=1);

use App\Filament\Pages\Auth\Login;
use App\Models\LoginAttempt;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| دخول لوحة الإدارة /admin/login بالجوال (T04 — docs/SPEC.md §3, §12.2)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

function attemptAdminLogin(string $phone, string $password): Testable
{
    return Livewire::test(Login::class)
        ->fillForm(['phone' => $phone, 'password' => $password])
        ->call('authenticate');
}

test('صفحة دخول اللوحة تطلب الجوال وكلمة المرور ولا تطلب بريدًا', function (): void {
    $this->get('/admin/login')
        ->assertOk()
        ->assertSeeLivewire(Login::class)
        ->assertSee('رقم الجوال')
        ->assertSee('كلمة المرور')
        ->assertSee('type="tel"', false)
        ->assertDontSee('type="email"', false);
});

test('المدير يدخل اللوحة بجواله وكلمة مروره', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptAdminLogin('0512345678', 'S3cure-pass')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    expect(Auth::id())->toBe($admin->id);
});

test('المشرف يدخل اللوحة ويُسجَّل دخوله الناجح في login_attempts', function (): void {
    $supervisor = User::factory()->supervisor()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptAdminLogin('0512345678', 'S3cure-pass')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getUrl());

    expect(Auth::id())->toBe($supervisor->id)
        ->and(LoginAttempt::query()->sole()->succeeded)->toBeTrue();
});

test('الجوال بالأرقام العربية الهندية يُقبل في دخول اللوحة', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptAdminLogin('٠٥١٢٣٤٥٦٧٨', 'S3cure-pass')->assertHasNoFormErrors();

    expect(Auth::id())->toBe($admin->id);
});

test('المبادر يُحوَّل إلى /dashboard ولا يدخل اللوحة', function (): void {
    $user = User::factory()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptAdminLogin('0512345678', 'S3cure-pass')
        ->assertHasNoFormErrors()
        ->assertRedirect(route('dashboard'));

    expect(Auth::id())->toBe($user->id);

    $this->get('/admin')->assertRedirect(route('dashboard'));
});

test('الحساب المعطَّل يُمنع برسالة التواصل مع الإدارة', function (string $role): void {
    User::factory()->inactive()->create([
        'phone' => '+966512345678',
        'password' => 'S3cure-pass',
        'role' => $role,
    ]);

    attemptAdminLogin('0512345678', 'S3cure-pass')
        ->assertHasErrors(['data.phone'])
        ->assertSee('حسابك معطَّل. تواصل مع إدارة المبادرة.');

    expect(Auth::check())->toBeFalse();
})->with(['admin', 'supervisor', 'user']);

test('بيانات خاطئة تُرفض برسالة موحّدة ولا تكشف أي الحقلين خاطئ', function (string $phone, string $password): void {
    User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    attemptAdminLogin($phone, $password)
        ->assertHasErrors(['data.phone'])
        ->assertSee('بيانات الدخول غير صحيحة')
        ->assertSet('data.password', null);

    expect(Auth::check())->toBeFalse()
        ->and(LoginAttempt::query()->sole()->succeeded)->toBeFalse();
})->with([
    'كلمة مرور خاطئة' => ['0512345678', 'wrong-pass'],
    'رقم غير مسجّل' => ['0598765432', 'S3cure-pass'],
    'رقم غير سعودي' => ['+201001234567', 'S3cure-pass'],
]);

test('الدخول يُقفل مؤقتًا بعد 5 محاولات فاشلة كما في /login', function (): void {
    User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    foreach (range(1, 5) as $attempt) {
        attemptAdminLogin('0512345678', 'wrong-pass')->assertSee('بيانات الدخول غير صحيحة');
    }

    attemptAdminLogin('0512345678', 'S3cure-pass')
        ->assertHasErrors(['data.phone'])
        ->assertSee('أُوقف الدخول مؤقتًا');

    expect(Auth::check())->toBeFalse();
});

test('الحقلان مطلوبان', function (): void {
    attemptAdminLogin('', '')
        ->assertHasFormErrors(['phone' => 'required', 'password' => 'required']);
});
