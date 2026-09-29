<?php

declare(strict_types=1);

use App\Actions\Admin\AcceptAdminInvite;
use App\Actions\Admin\CompleteAdminPasswordReset;
use App\Actions\Admin\InviteAdmin;
use App\Actions\Admin\IssueAdminResetLink;
use App\Actions\Supervisors\AcceptSupervisorInvite;
use App\Actions\Supervisors\InviteSupervisor;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetAdminPassword;
use App\Livewire\JoinAdmin;
use App\Livewire\JoinSupervisor;
use App\Models\AdminInvite;
use App\Models\AdminPasswordReset;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\SupervisorInvite;
use App\Models\User;
use App\Support\LinkPhoneConfirmation;
use App\Support\SessionEpoch;
use App\UserRole;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| الوضع المبسّط TWO_FACTOR_REQUIRED=false (docs/DECISIONS.md)
|--------------------------------------------------------------------------
| الدعوة والاستعادة بتأكيد رقم الجوال وكلمة مرور فقط، والدخول بالجوال وكلمة المرور.
*/

beforeEach(function (): void {
    config(['security.two_factor.required' => false]);
    $this->seed(PermissionSeeder::class);
});

function simpleJoinAdmin(string $token, string $phone, string $password = 'Secure#pass1'): Testable
{
    return Livewire::test(JoinAdmin::class, ['token' => $token])
        ->set('phone', $phone)
        ->set('full_name', 'سالم ماجد تركي العجاوني')
        ->set('password', $password)
        ->set('password_confirmation', $password)
        ->call('join');
}

function simpleSupervisorToken(string $whatsappUrl): string
{
    preg_match('#/join/([A-Za-z0-9\-_]+)#', rawurldecode($whatsappUrl), $matches);

    return $matches[1];
}

describe('دعوة المدير', function (): void {
    test('الصفحة تطلب رقم الجوال ولا تعرض رقم الدعوة ولا QR', function (): void {
        $result = app(InviteAdmin::class)->handle('0511111201');

        Livewire::test(JoinAdmin::class, ['token' => $result['plain_token']])
            ->assertSet('invalid', false)
            ->assertSet('phone', '')
            ->assertSee('wire:model="phone"', false)
            ->assertDontSee('+966511111201')
            ->assertDontSee('0511111201')
            ->assertDontSee(__('auth.two_factor.enroll.heading'))
            ->assertDontSee('two_factor_code', false);
    });

    test('الرقم الصحيح وكلمة المرور تنشئ الحساب بلا تحقق بخطوتين وتدخله اللوحة', function (): void {
        $result = app(InviteAdmin::class)->handle('0511111202');

        simpleJoinAdmin($result['plain_token'], '0511111202')
            ->assertHasNoErrors()
            ->assertRedirect();

        $admin = User::query()->where('phone', '+966511111202')->sole();

        expect($admin->role)->toBe(UserRole::Admin)
            ->and($admin->hasTwoFactorEnabled())->toBeFalse()
            ->and(Auth::id())->toBe($admin->id)
            ->and($result['invite']->fresh()->accepted_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', InviteAdmin::AUDIT_ACTION)->exists())->toBeTrue()
            ->and(AuditLog::query()->where('action', AcceptAdminInvite::AUDIT_ACTION)->sole()->meta)
            ->toMatchArray(['two_factor_enabled' => false]);

        $this->get(adminPath())->assertOk();
    });

    test('الرابط صالح 48 ساعة، ثم يُرفض', function (): void {
        Carbon::setTestNow('2026-09-29 12:00:00');

        $result = app(InviteAdmin::class)->handle('0511111203');

        expect($result['invite']->fresh()->expires_at->equalTo(now()->addHours(48)))->toBeTrue();

        Carbon::setTestNow(now()->addHours(48)->addSecond());

        Livewire::test(JoinAdmin::class, ['token' => $result['plain_token']])
            ->assertSet('invalid', true);

        simpleJoinAdmin($result['plain_token'], '0511111203')->assertHasErrors(['form']);

        expect(User::query()->where('phone', '+966511111203')->exists())->toBeFalse();
    });

    test('الرابط يعمل مرة واحدة فقط', function (): void {
        $result = app(InviteAdmin::class)->handle('0511111204');

        simpleJoinAdmin($result['plain_token'], '0511111204')->assertHasNoErrors();
        Auth::logout();

        Livewire::test(JoinAdmin::class, ['token' => $result['plain_token']])
            ->assertSet('invalid', true);

        simpleJoinAdmin($result['plain_token'], '0511111204')->assertHasErrors(['form']);

        expect(User::query()->where('phone', '+966511111204')->count())->toBe(1);
    });

    test('كلمة المرور الضعيفة تُرفض: 8 على الأقل بحروف وأرقام ورموز', function (string $password): void {
        $result = app(InviteAdmin::class)->handle('0511111205');

        simpleJoinAdmin($result['plain_token'], '0511111205', $password)->assertHasErrors(['password']);

        expect(User::query()->where('phone', '+966511111205')->exists())->toBeFalse();
    })->with([
        'قصيرة' => ['Ab#1234'],
        'بلا رموز' => ['Abcdefg12'],
        'بلا أرقام' => ['Abcdefg#!'],
        'بلا حروف' => ['12345678#'],
    ]);
});

describe('رفض الرقم الخطأ', function (): void {
    test('الرقم الخطأ يُرفض برسالة عامة لا تكشف الرقم الصحيح ولا يُنشئ حسابًا', function (): void {
        $result = app(InviteAdmin::class)->handle('0511111301');

        simpleJoinAdmin($result['plain_token'], '0599999999')
            ->assertHasErrors(['phone'])
            ->assertSee(__('admin.errors.phone_mismatch'))
            ->assertDontSee('0511111301')
            ->assertDontSee('+966511111301')
            ->assertSet('invalid', false)
            ->assertSet('password', '');

        expect(User::query()->count())->toBe(0)
            ->and($result['invite']->fresh()->isUsable())->toBeTrue();
    });

    test('بعد 5 أرقام خاطئة يُلغى الرابط ولا يقبل حتى الرقم الصحيح', function (): void {
        $result = app(InviteAdmin::class)->handle('0511111302');

        foreach (range(1, 5) as $attempt) {
            simpleJoinAdmin($result['plain_token'], '059999999'.$attempt)->assertHasErrors(['phone']);
        }

        expect($result['invite']->fresh()->isUsable())->toBeFalse()
            ->and(AuditLog::query()->where('action', LinkPhoneConfirmation::REVOKED_AUDIT_ACTION)->where('subject_id', $result['invite']->id)->exists())->toBeTrue();

        $this->travel(1)->seconds();

        Livewire::test(JoinAdmin::class, ['token' => $result['plain_token']])->assertSet('invalid', true);
        simpleJoinAdmin($result['plain_token'], '0511111302')->assertHasErrors(['form']);

        expect(User::query()->where('phone', '+966511111302')->exists())->toBeFalse();
    });

    test('الإجراء نفسه يرفض قبول الدعوة دون تأكيد الرقم', function (): void {
        $result = app(InviteAdmin::class)->handle('0511111303');

        expect(fn () => app(AcceptAdminInvite::class)->handle($result['plain_token'], 'سالم ماجد تركي العجاوني', 'Secure#pass1', '127.0.0.1', null))
            ->toThrow(InvalidArgumentException::class);

        expect(User::query()->count())->toBe(0);
    });
});

describe('دعوة المشرف من اللوحة', function (): void {
    test('تعمل بالطريقة نفسها: 48 ساعة، تأكيد الرقم، وبلا تحقق بخطوتين', function (): void {
        Carbon::setTestNow('2026-09-29 12:00:00');
        $admin = User::factory()->admin()->create();

        $result = app(InviteSupervisor::class)->handle($admin, '0511111401', ['users.view']);
        $token = simpleSupervisorToken($result['whatsapp_url']);

        expect($result['invite']->fresh()->expires_at->equalTo(now()->addHours(48)))->toBeTrue();

        Livewire::test(JoinSupervisor::class, ['token' => $token])
            ->assertSet('phone', '')
            ->assertDontSee('+966511111401')
            ->set('phone', '0599999999')
            ->set('full_name', 'فهد سالم ماجد العجاوني')
            ->set('password', 'Secure#pass1')
            ->set('password_confirmation', 'Secure#pass1')
            ->call('join')
            ->assertHasErrors(['phone'])
            ->set('phone', '0511111401')
            ->set('password', 'Secure#pass1')
            ->set('password_confirmation', 'Secure#pass1')
            ->call('join')
            ->assertHasNoErrors()
            ->assertRedirect();

        $supervisor = User::query()->where('phone', '+966511111401')->sole();

        expect($supervisor->role)->toBe(UserRole::Supervisor)
            ->and($supervisor->hasTwoFactorEnabled())->toBeFalse()
            ->and($supervisor->hasPermissionTo('users.view'))->toBeTrue()
            ->and(SupervisorInvite::query()->sole()->accepted_at)->not->toBeNull()
            ->and(AuditLog::query()->where('action', AcceptSupervisorInvite::AUDIT_ACTION)->sole()->meta)
            ->toMatchArray(['two_factor_enabled' => false]);

        Auth::logout();

        Livewire::test(JoinSupervisor::class, ['token' => $token])->assertSet('invalid', true);
    });

    test('الوضع الإلزامي يُبقي صلاحية دعوة المشرف 72 ساعة', function (): void {
        config(['security.two_factor.required' => true]);
        Carbon::setTestNow('2026-09-29 12:00:00');

        $result = app(InviteSupervisor::class)->handle(User::factory()->admin()->create(), '0511111402', ['users.view']);

        expect($result['invite']->fresh()->expires_at->equalTo(now()->addHours(72)))->toBeTrue();
    });
});

describe('الدخول', function (): void {
    test('المدير والمشرف يدخلان بالجوال وكلمة المرور فقط', function (string $role): void {
        $user = User::factory()->{$role}()->withoutTwoFactor()->create(['phone' => '+966512345678', 'password' => 'Secure#pass1']);

        Livewire::test(Login::class)
            ->set('phone', '0512345678')
            ->set('password', 'Secure#pass1')
            ->call('login')
            ->assertHasNoErrors()
            ->assertSet('challengedUser', null)
            ->assertRedirect($user->homeUrl());

        expect(Auth::id())->toBe($user->id)
            ->and(LoginAttempt::query()->where('succeeded', true)->count())->toBe(1);
    })->with(['admin', 'supervisor']);

    test('حساب فعّل التحقق سابقًا لا ينكسر ويدخل دون رمز', function (): void {
        $admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'Secure#pass1']);

        expect($admin->hasTwoFactorEnabled())->toBeTrue();

        Livewire::test(Login::class)
            ->set('phone', '0512345678')
            ->set('password', 'Secure#pass1')
            ->call('login')
            ->assertHasNoErrors()
            ->assertSet('challengedUser', null)
            ->assertRedirect($admin->homeUrl());

        $this->get(adminPath())->assertOk();
    });

    test('حدود محاولات الدخول تبقى كما هي', function (): void {
        User::factory()->admin()->withoutTwoFactor()->create(['phone' => '+966512345678', 'password' => 'Secure#pass1']);

        foreach (range(1, (int) config('security.login.max_attempts')) as $attempt) {
            Livewire::test(Login::class)->set('phone', '0512345678')->set('password', 'wrong-pass')->call('login');
        }

        Livewire::test(Login::class)
            ->set('phone', '0512345678')
            ->set('password', 'Secure#pass1')
            ->call('login')
            ->assertHasErrors(['phone']);

        expect(Auth::check())->toBeFalse();
    });

    test('Turnstile مفعَّل يمنع الدخول برمز مرفوض ويسمح برمز صالح', function (): void {
        config([
            'services.turnstile.enabled' => true,
            'services.turnstile.site_key' => 'test-site-key',
            'services.turnstile.secret_key' => 'test-secret-key',
        ]);
        User::factory()->admin()->withoutTwoFactor()->create(['phone' => '+966512345678', 'password' => 'Secure#pass1']);

        $this->get('/login')->assertOk()->assertSee('test-site-key', false);

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => false])]);

        Livewire::test(Login::class)
            ->set('phone', '0512345678')
            ->set('password', 'Secure#pass1')
            ->set('turnstileToken', 'invalid-token')
            ->call('login')
            ->assertHasErrors(['turnstile'])
            ->assertDispatched('turnstile-reset');

        expect(Auth::check())->toBeFalse()
            ->and(LoginAttempt::query()->count())->toBe(0);

        Http::fake(['challenges.cloudflare.com/*' => Http::response(['success' => true])]);

        Livewire::test(Login::class)
            ->set('phone', '0512345678')
            ->set('password', 'Secure#pass1')
            ->set('turnstileToken', 'valid-token')
            ->call('login')
            ->assertHasNoErrors();

        expect(Auth::check())->toBeTrue();
    });
});

describe('الاستعادة', function (): void {
    test('admin:reset-link وadmin:reset-password يصدران رابطًا لمدير أو مشرف صالحًا 48 ساعة ويُسجَّل', function (string $command, string $role): void {
        $user = User::factory()->{$role}()->withoutTwoFactor()->create(['phone' => '+966512345678']);

        $this->artisan($command, ['phone' => '0512345678'])
            ->expectsOutputToContain('/admin-reset/')
            ->expectsOutputToContain('https://wa.me/966512345678')
            ->expectsOutputToContain('48 ساعة')
            ->assertSuccessful();

        $reset = AdminPasswordReset::query()->sole();

        expect($reset->user_id)->toBe($user->id)
            ->and((int) round($reset->expires_at->diffInMinutes(now(), true)))->toBe(48 * 60)
            ->and(AuditLog::query()->where('action', IssueAdminResetLink::AUDIT_ACTION)->where('subject_id', $user->id)->exists())->toBeTrue();
    })->with([
        'مدير' => ['admin:reset-link', 'admin'],
        'مشرف' => ['admin:reset-link', 'supervisor'],
        'الاسم البديل' => ['admin:reset-password', 'supervisor'],
    ]);

    test('المبادر لا يحصل على رابط', function (): void {
        User::factory()->create(['phone' => '+966512345678']);

        $this->artisan('admin:reset-link', ['phone' => '0512345678'])->assertFailed();

        expect(AdminPasswordReset::query()->count())->toBe(0);
    });

    test('الرابط يعيّن كلمة المرور بعد تأكيد الرقم، مرة واحدة، وينهي كل الجلسات', function (): void {
        $supervisor = User::factory()->supervisor()->withoutTwoFactor()->create(['phone' => '+966512345678']);
        $epoch = SessionEpoch::current($supervisor);
        $rememberToken = $supervisor->remember_token;
        $result = app(IssueAdminResetLink::class)->handle('0512345678');

        Livewire::test(ResetAdminPassword::class, ['token' => $result['plain_token']])
            ->assertSee('wire:model="phone"', false)
            ->assertDontSee('0512345678')
            ->set('phone', '0599999999')
            ->set('password', 'N3w#secure')
            ->set('password_confirmation', 'N3w#secure')
            ->call('resetPassword')
            ->assertHasErrors(['phone'])
            ->assertSet('invalid', false)
            ->set('phone', '0512345678')
            ->set('password', 'N3w#secure')
            ->set('password_confirmation', 'N3w#secure')
            ->call('resetPassword')
            ->assertHasNoErrors()
            ->assertRedirect(route('login'));

        $supervisor->refresh();

        expect(Hash::check('N3w#secure', $supervisor->password))->toBeTrue()
            ->and(SessionEpoch::current($supervisor))->toBe($epoch + 1)
            ->and($supervisor->remember_token)->not->toBe($rememberToken)
            ->and(AuditLog::query()->where('action', CompleteAdminPasswordReset::AUDIT_ACTION)->where('subject_id', $supervisor->id)->exists())->toBeTrue();

        $this->actingAs($supervisor)
            ->withSession([SessionEpoch::SESSION_KEY => $epoch])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        Livewire::test(ResetAdminPassword::class, ['token' => $result['plain_token']])
            ->assertSet('invalid', true);
    });

    test('بعد 5 أرقام خاطئة يُلغى رابط الاستعادة', function (): void {
        $admin = User::factory()->admin()->withoutTwoFactor()->create(['phone' => '+966512345678']);
        $result = app(IssueAdminResetLink::class)->handle('0512345678');

        foreach (range(1, 5) as $attempt) {
            Livewire::test(ResetAdminPassword::class, ['token' => $result['plain_token']])
                ->set('phone', '059999999'.$attempt)
                ->set('password', 'N3w#secure')
                ->set('password_confirmation', 'N3w#secure')
                ->call('resetPassword')
                ->assertHasErrors(['phone']);
        }

        expect($result['reset']->fresh()?->isUsable())->toBeFalse()
            ->and(Hash::check('N3w#secure', (string) $admin->fresh()?->password))->toBeFalse();
    });

    test('رابط الاستعادة ينتهي بعد 48 ساعة', function (): void {
        User::factory()->admin()->withoutTwoFactor()->create(['phone' => '+966512345678']);
        $result = app(IssueAdminResetLink::class)->handle('0512345678');

        $this->travel(47)->hours();

        Livewire::test(ResetAdminPassword::class, ['token' => $result['plain_token']])->assertSet('invalid', false);

        $this->travel(2)->hours();

        Livewire::test(ResetAdminPassword::class, ['token' => $result['plain_token']])->assertSet('invalid', true);
    });

    test('الوضع الإلزامي يُبقي الاستعادة للمدير فقط وصالحة 30 دقيقة', function (): void {
        config(['security.two_factor.required' => true]);
        User::factory()->supervisor()->create(['phone' => '+966512345601']);
        User::factory()->admin()->create(['phone' => '+966512345602']);

        $this->artisan('admin:reset-link', ['phone' => '0512345601'])
            ->expectsOutputToContain('لا يوجد مدير مسجّل بهذا الرقم.')
            ->assertFailed();

        $result = app(IssueAdminResetLink::class)->handle('0512345602');

        expect((int) round($result['reset']->expires_at->diffInMinutes(now(), true)))->toBe(30);
    });
});

describe('تغيير APP_URL', function (): void {
    test('لا يؤثر على الحسابات الموجودة ولا على الروابط الصادرة', function (): void {
        $admin = User::factory()->admin()->withoutTwoFactor()->create(['phone' => '+966512345678', 'password' => 'Secure#pass1']);
        $invite = app(InviteAdmin::class)->handle('0511111501');

        config(['app.url' => 'https://new-domain.example']);
        URL::forceRootUrl('https://new-domain.example');

        Livewire::test(Login::class)
            ->set('phone', '0512345678')
            ->set('password', 'Secure#pass1')
            ->call('login')
            ->assertHasNoErrors();

        expect(Auth::id())->toBe($admin->id);

        Auth::logout();

        simpleJoinAdmin($invite['plain_token'], '0511111501')->assertHasNoErrors();

        expect(AdminInvite::query()->sole()->accepted_at)->not->toBeNull()
            ->and(User::query()->where('phone', '+966511111501')->exists())->toBeTrue();
    });
});
