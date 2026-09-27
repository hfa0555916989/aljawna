<?php

declare(strict_types=1);

use App\Actions\Admin\InviteAdmin;
use App\Actions\Auth\ResetTwoFactor;
use App\Actions\Supervisors\InviteSupervisor;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\SetUpTwoFactor;
use App\Livewire\JoinAdmin;
use App\Livewire\JoinSupervisor;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\TwoFactorSetupLink;
use App\Models\User;
use App\Support\ReservedSlugs;
use App\Support\TwoFactorEnrollment;
use App\Support\TwoFactorSession;
use App\UserRole;
use Database\Seeders\PermissionSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| إعداد التحقق بخطوتين قبل أي وصول (docs/DECISIONS.md، T20)
|--------------------------------------------------------------------------
| في صفحتي قبول الدعوة قبل إنشاء الحساب، وفي رابط admin:reset-2fa قبل الدخول.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

function supervisorInviteToken(User $admin, string $phone): string
{
    $url = app(InviteSupervisor::class)->handle($admin, $phone, ['users.view'])['whatsapp_url'];

    preg_match('#/join/([A-Za-z0-9\-_]+)#', rawurldecode($url), $matches);

    return $matches[1];
}

function fillJoinForm(string $component, string $token): Testable
{
    return Livewire::test($component, ['token' => $token])
        ->set('full_name', 'سالم ماجد تركي العجاوني')
        ->set('password', 'Secretpass1')
        ->set('password_confirmation', 'Secretpass1');
}

test('صفحتا قبول الدعوة تعرضان رمز QR ومفتاحًا يدويًا دون وضع السر في حالة Livewire', function (string $component, string $context): void {
    $token = $component === JoinSupervisor::class
        ? supervisorInviteToken($this->admin, '0511112001')
        : app(InviteAdmin::class)->handle('0511112001')['plain_token'];

    $testable = Livewire::test($component, ['token' => $token])
        ->assertSee('إعداد التحقق بخطوتين')
        ->assertSee('data:image/', false)
        ->assertSee('autocomplete="one-time-code"', false);

    $secret = TwoFactorEnrollment::pendingSecret($context.$token);

    expect(json_encode($testable->snapshot))->not->toContain($secret)
        ->and($testable->html())->toContain(TwoFactorEnrollment::groupedSecret($secret));
})->with([
    'مشرف' => [JoinSupervisor::class, 'join:supervisor:'],
    'مدير' => [JoinAdmin::class, 'join:admin:'],
]);

test('الرمز الخاطئ أو الفارغ لا يُنشئ الحساب ولا يستهلك الدعوة', function (string $component, string $code): void {
    $isSupervisor = $component === JoinSupervisor::class;
    $token = $isSupervisor ? supervisorInviteToken($this->admin, '0511112002') : app(InviteAdmin::class)->handle('0511112002')['plain_token'];

    fillJoinForm($component, $token)
        ->set('two_factor_code', $code)
        ->call('join')
        ->assertHasErrors(['two_factor_code'])
        ->assertSet('invalid', false);

    expect(User::query()->where('phone', '+966511112002')->exists())->toBeFalse()
        ->and(Auth::check())->toBeFalse();
})->with([
    'مشرف برمز خاطئ' => [JoinSupervisor::class, '000000'],
    'مدير برمز خاطئ' => [JoinAdmin::class, '000000'],
    'مشرف بلا رمز' => [JoinSupervisor::class, ''],
    'مدير بحروف' => [JoinAdmin::class, 'abcdef'],
]);

test('قبول الدعوة برمز صحيح يُنشئ الحساب والتحقق مفعَّل فيه، ويعرض رموز الاسترداد مرة واحدة', function (string $component, string $context, UserRole $role): void {
    $token = $component === JoinSupervisor::class
        ? supervisorInviteToken($this->admin, '0511112003')
        : app(InviteAdmin::class)->handle('0511112003')['plain_token'];

    $testable = fillJoinForm($component, $token)
        ->set('two_factor_code', pendingTwoFactorCode($context.$token))
        ->call('join')
        ->assertHasNoErrors()
        ->assertSee('رموز الاسترداد')
        ->assertSet('password', '');

    $user = User::query()->where('phone', '+966511112003')->sole();
    $codes = $testable->get('recoveryCodes');

    expect($user->role)->toBe($role)
        ->and($user->hasTwoFactorEnabled())->toBeTrue()
        ->and($codes)->toHaveCount(8)
        ->and(AppAuthentication::make()->recoverable()->verifyRecoveryCode($codes[0], $user->fresh()))->toBeTrue()
        ->and(Auth::id())->toBe($user->id)
        ->and(session(TwoFactorSession::SESSION_KEY))->toBeTrue()
        ->and(AuditLog::query()->where('subject_id', $user->id)->whereIn('action', ['supervisor.joined', 'admin.joined'])->sole()->meta['two_factor_enabled'])->toBeTrue();

    $this->get(adminPath())->assertOk();
})->with([
    'مشرف' => [JoinSupervisor::class, 'join:supervisor:', UserRole::Supervisor],
    'مدير' => [JoinAdmin::class, 'join:admin:', UserRole::Admin],
]);

test('من قبل الدعوة يدخل لاحقًا بكلمة المرور ثم رمز تطبيقه', function (): void {
    $token = app(InviteAdmin::class)->handle('0511112004')['plain_token'];

    fillJoinForm(JoinAdmin::class, $token)
        ->set('two_factor_code', pendingTwoFactorCode('join:admin:'.$token))
        ->call('join')
        ->assertHasNoErrors();

    Auth::logout();
    $user = User::query()->where('phone', '+966511112004')->sole();

    // Google2FA يقرأ ساعة PHP لا Carbon، ومنع إعادة الاستخدام يرفض رمز الإعداد نفسه
    // عمدًا؛ مسح سجل آخر رمز مقبول يحاكي دخولًا في خطوة زمنية لاحقة.
    Cache::flush();

    Livewire::test(Login::class)
        ->set('phone', '0511112004')
        ->set('password', 'Secretpass1')
        ->call('login')
        ->assertNotSet('challengedUser', null)
        ->set('code', AppAuthentication::make()->getCurrentCode($user))
        ->call('verifyTwoFactor')
        ->assertHasNoErrors()
        ->assertRedirect(url(adminPath()));
});

/*
|--------------------------------------------------------------------------
| رابط الإعداد من admin:reset-2fa
|--------------------------------------------------------------------------
*/

function resetTwoFactorFor(User $user): string
{
    $url = (string) app(ResetTwoFactor::class)->handle($user->phone)['setup_url'];

    return substr($url, strrpos($url, '/') + 1);
}

test('رابط الإعداد 32 بايت، مخزَّن مجزّأً، ويُبطل الإصدارُ الجديد السابق', function (): void {
    $supervisor = User::factory()->supervisor()->create();

    $first = resetTwoFactorFor($supervisor);
    $second = resetTwoFactorFor($supervisor);

    $raw = base64_decode(strtr($second, '-_', '+/').str_repeat('=', (4 - strlen($second) % 4) % 4), true);

    expect(strlen((string) $raw))->toBe(32)
        ->and(TwoFactorSetupLink::query()->where('token_hash', $second)->exists())->toBeFalse()
        ->and(TwoFactorSetupLink::findByToken($first)?->isUsable())->toBeFalse()
        ->and(TwoFactorSetupLink::findByToken($second)?->isUsable())->toBeTrue();

    Livewire::test(SetUpTwoFactor::class, ['token' => $first])
        ->assertSet('invalid', true)
        ->assertSee('رابط الإعداد غير صالح');
});

test('الرابط وكلمة المرور ورمز صحيح: يُفعَّل التحقق ويدخل بجلسة مجتازة ويُستهلك الرابط', function (): void {
    $supervisor = User::factory()->supervisor()->create(['password' => 'S3cure-pass']);
    $token = resetTwoFactorFor($supervisor);

    $testable = Livewire::test(SetUpTwoFactor::class, ['token' => $token])
        ->assertSee('data:image/', false)
        ->set('password', 'S3cure-pass')
        ->set('two_factor_code', pendingTwoFactorCode('two-factor-setup:'.$token))
        ->call('setUp')
        ->assertHasNoErrors()
        ->assertSee('رموز الاسترداد');

    $supervisor->refresh();

    expect($supervisor->hasTwoFactorEnabled())->toBeTrue()
        ->and($testable->get('recoveryCodes'))->toHaveCount(8)
        ->and(TwoFactorSetupLink::findByToken($token)?->used_at)->not->toBeNull()
        ->and(Auth::id())->toBe($supervisor->id)
        ->and(session(TwoFactorSession::SESSION_KEY))->toBeTrue()
        ->and(AuditLog::query()->where('action', SetUpTwoFactor::AUDIT_ACTION)->sole()->actor_id)->toBe($supervisor->id)
        ->and(LoginAttempt::query()->where('succeeded', true)->count())->toBe(1);

    $this->get(adminPath())->assertOk();

    Livewire::test(SetUpTwoFactor::class, ['token' => $token])->assertSet('invalid', true);
});

test('كلمة المرور الخاطئة في صفحة الإعداد تُرفض وتُحتسب في قفل الدخول', function (): void {
    $supervisor = User::factory()->supervisor()->create(['password' => 'S3cure-pass']);
    $token = resetTwoFactorFor($supervisor);

    foreach (range(1, (int) config('security.login.max_attempts')) as $ignored) {
        Livewire::test(SetUpTwoFactor::class, ['token' => $token])
            ->set('password', 'wrong-pass')
            ->set('two_factor_code', pendingTwoFactorCode('two-factor-setup:'.$token))
            ->call('setUp')
            ->assertHasErrors(['password']);
    }

    Livewire::test(SetUpTwoFactor::class, ['token' => $token])
        ->set('password', 'S3cure-pass')
        ->set('two_factor_code', pendingTwoFactorCode('two-factor-setup:'.$token))
        ->call('setUp')
        ->assertHasErrors(['password'])
        ->assertSee('أُوقف الدخول مؤقتًا');

    expect($supervisor->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and(Auth::check())->toBeFalse();
});

test('الرابط وحده لا يكفي: رمز خاطئ أو كلمة مرور ناقصة لا تفعّل شيئًا', function (): void {
    $supervisor = User::factory()->supervisor()->create(['password' => 'S3cure-pass']);
    $token = resetTwoFactorFor($supervisor);

    Livewire::test(SetUpTwoFactor::class, ['token' => $token])
        ->set('password', 'S3cure-pass')
        ->set('two_factor_code', '000000')
        ->call('setUp')
        ->assertHasErrors(['two_factor_code']);

    Livewire::test(SetUpTwoFactor::class, ['token' => $token])
        ->set('two_factor_code', pendingTwoFactorCode('two-factor-setup:'.$token))
        ->call('setUp')
        ->assertHasErrors(['password']);

    expect($supervisor->fresh()->hasTwoFactorEnabled())->toBeFalse()
        ->and(TwoFactorSetupLink::findByToken($token)?->isUsable())->toBeTrue()
        ->and(Auth::check())->toBeFalse();
});

test('الرابط ينتهي بعد مدته، ولا يعمل لحساب معطَّل', function (): void {
    $supervisor = User::factory()->supervisor()->create();
    $token = resetTwoFactorFor($supervisor);

    $this->travel((int) config('security.two_factor.setup_link_minutes') + 1)->minutes();

    Livewire::test(SetUpTwoFactor::class, ['token' => $token])->assertSet('invalid', true);

    $this->travelBack();
    $other = User::factory()->supervisor()->create();
    $otherToken = resetTwoFactorFor($other);
    $other->forceFill(['is_active' => false])->save();

    Livewire::test(SetUpTwoFactor::class, ['token' => $otherToken])->assertSet('invalid', true);
});

test('مسار رابط الإعداد لا يظهر في أي صفحة ومساره محجوز من منشئ الصفحات', function (): void {
    expect(route('two-factor.setup', ['token' => 'x'], false))->toBe('/two-factor/setup/x')
        ->and(ReservedSlugs::isReserved('two-factor'))->toBeTrue();

    $this->get('/')->assertDontSee('/two-factor/setup', false);
});
