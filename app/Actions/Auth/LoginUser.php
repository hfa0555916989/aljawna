<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\LoginAttempt;
use App\Models\User;
use App\Services\LoginThrottle;
use App\Support\SaudiPhone;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * التحقق من بيانات دخول المبادر بالجوال وكلمة المرور (docs/SPEC.md §3, §12.2).
 * يسجّل كل محاولة في login_attempts، ولا يكشف أي الحقلين خاطئ.
 */
class LoginUser
{
    public function __construct(private LoginThrottle $throttle) {}

    /**
     * @throws ValidationException
     */
    public function handle(string $phoneInput, string $password, string $ip): User
    {
        $phone = SaudiPhone::normalize($phoneInput);
        $identifier = $phone ?? mb_substr(trim($phoneInput), 0, 32);

        $lockedSeconds = $this->throttle->lockedSeconds($identifier, $ip);

        if ($lockedSeconds > 0) {
            $this->recordAttempt($identifier, $ip, succeeded: false);

            throw ValidationException::withMessages([
                'phone' => __('auth.locked', ['minutes' => (int) ceil($lockedSeconds / 60)]),
            ]);
        }

        $user = $phone === null ? null : User::query()->where('phone', $phone)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->recordAttempt($identifier, $ip, succeeded: false);
            $this->throttle->recordFailure($identifier, $ip);

            throw ValidationException::withMessages(['phone' => __('auth.failed')]);
        }

        if (! $user->is_active) {
            $this->recordAttempt($identifier, $ip, succeeded: false);

            throw ValidationException::withMessages(['phone' => __('auth.inactive')]);
        }

        $this->recordAttempt($identifier, $ip, succeeded: true);
        $this->throttle->clearPhone($identifier);

        $user->forceFill([
            'last_login_ip' => $ip,
            'last_login_at' => now(),
        ])->save();

        return $user;
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
