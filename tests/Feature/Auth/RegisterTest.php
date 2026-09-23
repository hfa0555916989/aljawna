<?php

declare(strict_types=1);

use App\Livewire\Auth\Register;
use App\Models\User;
use App\UserRole;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| التسجيل (T02 — docs/SPEC.md §3, FR-1..6, §12.3)
|--------------------------------------------------------------------------
*/

/**
 * @param  array<string, string>  $overrides
 */
function registerForm(array $overrides = []): Testable
{
    $values = array_merge([
        'full_name' => 'أحمد محمد علي العجاوني',
        'phone' => '0512345678',
        'phone_confirmation' => '0512345678',
        'password' => 'S3cure-pass',
        'password_confirmation' => 'S3cure-pass',
    ], $overrides);

    $component = Livewire::test(Register::class);

    foreach ($values as $property => $value) {
        $component->set($property, $value);
    }

    return $component;
}

test('صفحة التسجيل تُعرض بالحقول المطلوبة دون بريد', function (): void {
    $this->get('/register')
        ->assertOk()
        ->assertSeeLivewire(Register::class)
        ->assertSee('اكتب اسمك كاملًا')
        ->assertSee('for="full_name"', false)
        ->assertSee('for="phone"', false)
        ->assertSee('for="phone_confirmation"', false)
        ->assertSee('for="password"', false)
        ->assertSee('for="password_confirmation"', false)
        ->assertDontSee('type="email"', false);
});

test('التسجيل الناجح ينشئ حسابًا فعّالًا بجوال مطبَّع ويدخل إلى اللوحة', function (): void {
    registerForm()
        ->call('register')
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard'));

    $user = User::query()->sole();

    expect($user->full_name)->toBe('أحمد محمد علي العجاوني')
        ->and($user->phone)->toBe('+966512345678')
        ->and($user->role)->toBe(UserRole::User)
        ->and($user->is_active)->toBeTrue()
        ->and($user->registered_ip)->toBe('127.0.0.1')
        ->and(Hash::check('S3cure-pass', $user->password))->toBeTrue();

    expect(Auth::id())->toBe($user->id);
});

test('يقبل الجوال بالأرقام العربية وبصيغة دولية في التأكيد', function (): void {
    registerForm(['phone' => '٠٥١٢٣٤٥٦٧٨', 'phone_confirmation' => '+966 51 234 5678'])
        ->call('register')
        ->assertHasNoErrors();

    expect(User::query()->sole()->phone)->toBe('+966512345678');
});

test('يرفض الجوال المكرر برسالة واضحة', function (): void {
    User::factory()->create(['phone' => '+966512345678']);

    registerForm()
        ->call('register')
        ->assertHasErrors(['phone'])
        ->assertSee('هذا الرقم مسجّل مسبقًا، سجّل دخولك أو استعد كلمة المرور');

    expect(User::query()->count())->toBe(1);
});

test('يرفض عدم تطابق الجوال وتأكيده', function (): void {
    registerForm(['phone_confirmation' => '0512345679'])
        ->call('register')
        ->assertHasErrors(['phone_confirmation']);

    expect(User::query()->count())->toBe(0);
});

test('يرفض عدم تطابق كلمة المرور وتأكيدها', function (): void {
    registerForm(['password_confirmation' => 'Different-pass'])
        ->call('register')
        ->assertHasErrors(['password']);

    expect(User::query()->count())->toBe(0);
});

test('يرفض رقمًا غير سعودي', function (): void {
    registerForm(['phone' => '+971501234567', 'phone_confirmation' => '+971501234567'])
        ->call('register')
        ->assertHasErrors(['phone']);

    expect(User::query()->count())->toBe(0);
});

test('يرفض اسمًا أقل من أربع كلمات', function (): void {
    registerForm(['full_name' => 'أحمد محمد علي'])
        ->call('register')
        ->assertHasErrors(['full_name']);

    expect(User::query()->count())->toBe(0);
});

test('يرفض كلمة مرور أقصر من 8 أحرف', function (): void {
    registerForm(['password' => 'Ab1-xyz', 'password_confirmation' => 'Ab1-xyz'])
        ->call('register')
        ->assertHasErrors(['password' => 'min']);
});

test('يقبل كلمة مرور من 8 أحرف بالضبط', function (): void {
    registerForm(['password' => 'Ab1-wxyz', 'password_confirmation' => 'Ab1-wxyz'])
        ->call('register')
        ->assertHasNoErrors();
});

test('يرفض كلمة مرور تساوي رقم الجوال بأي صيغة', function (string $password): void {
    registerForm(['password' => $password, 'password_confirmation' => $password])
        ->call('register')
        ->assertHasErrors(['password']);

    expect(User::query()->count())->toBe(0);
})->with(['0512345678', '+966512345678', '00966512345678']);

test('الحقول المطلوبة الفارغة تُرفض', function (): void {
    Livewire::test(Register::class)
        ->call('register')
        ->assertHasErrors(['full_name', 'phone', 'phone_confirmation', 'password', 'password_confirmation']);
});

test('مؤشر تطابق الجوال يعكس الحالة فورًا', function (): void {
    $component = Livewire::test(Register::class)->set('phone', '0512345678');

    expect($component->instance()->phoneConfirmationMatches())->toBeNull();

    $component->set('phone_confirmation', '+966512345678')->assertSee('رقما الجوال متطابقان');
    $component->set('phone_confirmation', '0512345670')->assertSee('رقما الجوال غير متطابقين');
});

test('حقل الفخ المعبّأ يمنع التسجيل', function (): void {
    registerForm(['website' => 'https://spam.example'])
        ->call('register')
        ->assertHasErrors(['form']);

    expect(User::query()->count())->toBe(0);
});

test('حدّ التسجيل لكل IP في الساعة', function (): void {
    config(['security.register.max_per_ip_per_hour' => 2]);

    foreach (['0512345671', '0512345672'] as $phone) {
        registerForm(['phone' => $phone, 'phone_confirmation' => $phone])
            ->call('register')
            ->assertHasNoErrors();

        Auth::logout();
    }

    registerForm(['phone' => '0512345673', 'phone_confirmation' => '0512345673'])
        ->call('register')
        ->assertHasErrors(['form']);

    expect(User::query()->count())->toBe(2);

    $this->travel(61)->minutes();

    registerForm(['phone' => '0512345673', 'phone_confirmation' => '0512345673'])
        ->call('register')
        ->assertHasNoErrors();

    expect(User::query()->count())->toBe(3);
});

test('المحاولات الفاشلة تُحتسب ضمن حدّ التسجيل', function (): void {
    config(['security.register.max_per_ip_per_hour' => 2]);

    registerForm(['full_name' => 'أحمد محمد'])->call('register')->assertHasErrors(['full_name']);
    registerForm(['website' => 'https://spam.example'])->call('register')->assertHasErrors(['form']);

    registerForm()
        ->call('register')
        ->assertHasErrors(['form'])
        ->assertSee('تجاوزت عدد محاولات التسجيل');

    expect(User::query()->count())->toBe(0);
});

test('المستخدم المسجّل دخوله يُحوَّل من صفحة التسجيل إلى اللوحة', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/register')
        ->assertRedirect(route('dashboard'));
});

describe('Turnstile مفعَّل', function (): void {
    beforeEach(function (): void {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);
    });

    test('تعرض الصفحة عنصر Turnstile', function (): void {
        $this->get('/register')
            ->assertOk()
            ->assertSee('challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertSee('test-site-key', false);
    });

    test('رمز مرفوض من Cloudflare يمنع التسجيل ويعيد ضبط العنصر', function (): void {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        registerForm(['turnstileToken' => 'invalid-token'])
            ->call('register')
            ->assertHasErrors(['turnstile'])
            ->assertDispatched('turnstile-reset');

        expect(User::query()->count())->toBe(0);
    });

    test('غياب الرمز يمنع التسجيل دون اتصال بـ Cloudflare', function (): void {
        Http::fake();

        registerForm()
            ->call('register')
            ->assertHasErrors(['turnstile']);

        Http::assertNothingSent();
        expect(User::query()->count())->toBe(0);
    });

    test('رمز صالح يسمح بالتسجيل ويُرسل السر والرمز إلى Cloudflare', function (): void {
        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        registerForm(['turnstileToken' => 'valid-token'])
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        Http::assertSent(fn (Request $request): bool => $request['secret'] === 'test-secret-key'
            && $request['response'] === 'valid-token');

        expect(User::query()->count())->toBe(1);
    });
});
