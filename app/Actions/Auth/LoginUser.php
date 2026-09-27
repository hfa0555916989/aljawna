<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\LoginThrottle;
use App\Support\SaudiPhone;
use Closure;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * التحقق من بيانات الدخول بالجوال وكلمة المرور (docs/SPEC.md §3, §12.2).
 * يسجّل كل محاولة في login_attempts، ولا يكشف أي الحقلين خاطئ.
 *
 * لأدوار لوحة الإدارة خطوة ثانية (رمز TOTP أو رمز استرداد) تمرّ بنفس القفل
 * ونفس السجل، ولا يُسجَّل الدخول ناجحًا إلا بعد اجتيازها (docs/DECISIONS.md).
 */
class LoginUser
{
    public function __construct(private LoginThrottle $throttle) {}

    /**
     * دخول بخطوة واحدة: تحقق من البيانات ثم تسجيل النجاح.
     *
     * @throws ValidationException
     */
    public function handle(string $phoneInput, string $password, string $ip): User
    {
        $user = $this->verifyCredentials($phoneInput, $password, $ip);

        $this->recordSuccess($user, $ip);

        return $user;
    }

    /**
     * الخطوة الأولى: الجوال وكلمة المرور، دون تسجيل النجاح بعد.
     *
     * @throws ValidationException
     */
    public function verifyCredentials(string $phoneInput, string $password, string $ip): User
    {
        $phone = SaudiPhone::normalize($phoneInput);
        $identifier = $phone ?? mb_substr(trim($phoneInput), 0, 32);

        $this->ensureNotLocked($identifier, $ip, 'phone');

        $user = $phone === null ? null : User::query()->where('phone', $phone)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->recordFailure($identifier, $ip);

            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            $this->recordAttempt($identifier, $ip, succeeded: false);

            throw ValidationException::withMessages(['phone' => __('auth.inactive')]);
        }

        return $user;
    }

    /**
     * الخطوة الثانية لأدوار اللوحة: $verifier يتحقق من الرمز المُدخل. الرمز
     * الخاطئ محاولة فاشلة تُحتسب في القفل كما لو كانت كلمة مرور خاطئة.
     *
     * @param  Closure(): bool  $verifier
     *
     * @throws ValidationException
     */
    public function verifySecondFactor(User $user, string $ip, Closure $verifier, string $field): void
    {
        $this->ensureNotLocked($user->phone, $ip, $field);

        if (! $user->is_active) {
            $this->recordAttempt($user->phone, $ip, succeeded: false);

            throw ValidationException::withMessages([$field => __('auth.inactive')]);
        }

        if (! $verifier()) {
            $this->recordFailure($user->phone, $ip);

            throw ValidationException::withMessages([$field => __('auth.two_factor.invalid')]);
        }
    }

    public function recordSuccess(User $user, string $ip): void
    {
        $this->recordAttempt($user->phone, $ip, succeeded: true);
        $this->throttle->clearPhone($user->phone);

        $user->forceFill([
            'last_login_ip' => $ip,
            'last_login_at' => now(),
        ])->save();
    }

    /**
     * @throws ValidationException
     */
    private function ensureNotLocked(string $identifier, string $ip, string $field): void
    {
        $lockedSeconds = $this->throttle->lockedSeconds($identifier, $ip);

        if ($lockedSeconds > 0) {
            $this->recordAttempt($identifier, $ip, succeeded: false);

            throw ValidationException::withMessages([
                $field => __('auth.locked', ['minutes' => (int) ceil($lockedSeconds / 60)]),
            ]);
        }
    }

    private function recordFailure(string $identifier, string $ip): void
    {
        $this->recordAttempt($identifier, $ip, succeeded: false);
        $this->throttle->recordFailure($identifier, $ip);
    }

    private function recordAttempt(string $phone, string $ip, bool $succeeded): void
    {
        LoginAttempt::query()->create([
            'phone' => $phone,
            'ip' => $ip,
            'succeeded' => $succeeded,
        ]);
    }
}
