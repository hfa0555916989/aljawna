<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\LoginUser;
use App\Models\User;
use App\Services\Audit;
use App\Services\Turnstile;
use App\Support\TwoFactorEnrollment;
use App\Support\TwoFactorPolicy;
use App\Support\TwoFactorSession;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * صفحة الدخول الموحّدة /login للجميع بالجوال وكلمة المرور (docs/SPEC.md §3, FR-5).
 *
 * أدوار اللوحة (مشرف أو مدير) تمرّ بخطوة ثانية إن فعّلت التحقق بخطوتين: رمز من
 * تطبيق المصادقة أو رمز استرداد، يتحقق منه مزوّد Filament نفسه (AppAuthentication).
 * ومن لم يفعّله بعد لا يدخل بكلمة المرور وحدها، بل يُعدّه أولًا من رابط الدعوة أو
 * رابط الإعداد من admin:reset-2fa (LoginUser، docs/DECISIONS.md). ومتى كان
 * TWO_FACTOR_REQUIRED=false فلا خطوة ثانية لأحد (App\Support\TwoFactorPolicy).
 * Turnstile يسبق التحقق من كلمة المرور متى كان مفعَّلًا في الإعدادات.
 * بعد الدخول: أدوار اللوحة إلى اللوحة، والمبادر إلى /dashboard.
 */
#[Title('تسجيل الدخول')]
class Login extends Component
{
    /**
     * مهلة إدخال رمز الخطوة الثانية بعد كلمة المرور، ثم يبدأ الدخول من جديد.
     */
    public const int CHALLENGE_SECONDS = 600;

    public const string RECOVERY_CODE_AUDIT_ACTION = 'auth.recovery_code_used';

    public string $phone = '';

    public string $password = '';

    public string $code = '';

    public string $recoveryCode = '';

    public string $turnstileToken = '';

    public bool $useRecoveryCode = false;

    /**
     * معرّف صاحب الخطوة الثانية مشفَّرًا، كما في صفحة دخول Filament.
     */
    #[Locked]
    public ?string $challengedUser = null;

    public function login(LoginUser $loginUser, Turnstile $turnstile): void
    {
        $this->validate([
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        $ip = (string) request()->ip();

        if (! $turnstile->verify($this->turnstileToken, $ip)) {
            $this->reset('password');
            $this->resetTurnstile();

            throw ValidationException::withMessages(['turnstile' => __('auth.captcha_failed')]);
        }

        try {
            $user = $loginUser->verifyCredentials($this->phone, $this->password, $ip);
        } catch (ValidationException $exception) {
            $this->reset('password');
            $this->resetTurnstile();

            throw $exception;
        }

        $this->reset('password');

        if (TwoFactorPolicy::isRequired() && $user->hasPanelRole() && $user->hasTwoFactorEnabled()) {
            $this->challengedUser = Crypt::encryptString($user->getKey().'|'.now()->getTimestamp());

            return;
        }

        $loginUser->recordSuccess($user, $ip);

        $this->completeLogin($user, passedTwoFactor: false);
    }

    public function verifyTwoFactor(LoginUser $loginUser): void
    {
        $user = $this->challengedUserOrRestart();

        if ($user === null) {
            return;
        }

        $field = $this->useRecoveryCode ? 'recoveryCode' : 'code';

        $this->validate([
            $field => ['required', 'string', 'max:64'],
        ]);

        $provider = TwoFactorEnrollment::provider();
        $ip = (string) request()->ip();

        try {
            $loginUser->verifySecondFactor($user, $ip, fn (): bool => $this->useRecoveryCode
                ? $provider->verifyRecoveryCode(trim($this->recoveryCode), $user)
                : $provider->verifyCode(preg_replace('/\s+/', '', $this->code) ?? '', $provider->getSecret($user), shouldPreventCodeReuse: true),
                $field,
            );
        } catch (ValidationException $exception) {
            $this->reset('code', 'recoveryCode');

            throw $exception;
        }

        $loginUser->recordSuccess($user, $ip);

        if ($this->useRecoveryCode) {
            Audit::record(self::RECOVERY_CODE_AUDIT_ACTION, $user, [
                'remaining' => count($user->fresh()?->getAppAuthenticationRecoveryCodes() ?? []),
            ], $user);
        }

        $this->completeLogin($user, passedTwoFactor: true);
    }

    public function toggleRecoveryCode(): void
    {
        $this->useRecoveryCode = ! $this->useRecoveryCode;
        $this->reset('code', 'recoveryCode');
        $this->resetErrorBag();
    }

    public function cancelTwoFactor(): void
    {
        $this->reset();
        $this->resetErrorBag();
    }

    public function render(): View
    {
        $turnstile = app(Turnstile::class);

        return view('livewire.auth.login', [
            'turnstileSiteKey' => $turnstile->isEnabled() ? $turnstile->siteKey() : null,
        ]);
    }

    private function resetTurnstile(): void
    {
        $this->turnstileToken = '';
        $this->dispatch('turnstile-reset');
    }

    private function completeLogin(User $user, bool $passedTwoFactor): void
    {
        Auth::login($user);
        Session::regenerate();

        if ($passedTwoFactor) {
            TwoFactorSession::markPassed();
        }

        $this->redirect($this->destinationFor($user));
    }

    /**
     * أدوار اللوحة: الوجهة المقصودة إن كانت داخل اللوحة، وإلا اللوحة نفسها.
     * المبادر: الوجهة المقصودة ما لم تكن داخل اللوحة (ستعطيه 404)، وإلا /dashboard.
     */
    private function destinationFor(User $user): string
    {
        $intended = Session::pull('url.intended');
        $panelUrl = rtrim(url((string) config('admin.path')), '/');
        $isPanelUrl = is_string($intended) && ($intended === $panelUrl || str_starts_with($intended, $panelUrl.'/'));

        if ($user->hasPanelRole()) {
            return $isPanelUrl ? $intended : $user->homeUrl();
        }

        return is_string($intended) && ! $isPanelUrl ? $intended : $user->homeUrl();
    }

    private function challengedUserOrRestart(): ?User
    {
        $user = null;

        try {
            [$id, $issuedAt] = array_pad(explode('|', Crypt::decryptString((string) $this->challengedUser), 2), 2, '0');

            if (now()->getTimestamp() - (int) $issuedAt <= self::CHALLENGE_SECONDS) {
                $user = User::query()->find((int) $id);
            }
        } catch (DecryptException) {
            //
        }

        if (! $user instanceof User || ! TwoFactorPolicy::isRequired() || ! $user->hasPanelRole() || ! $user->hasTwoFactorEnabled()) {
            $this->reset();
            $this->addError('phone', __('auth.two_factor.expired'));

            return null;
        }

        return $user;
    }
}
