<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\UserRole;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء حساب مبادر فعّال فورًا (docs/SPEC.md §3, FR-1..6)، مع حدّ للتسجيل
 * لكل IP في الساعة (§12.3). المُدخلات مُتحقَّق منها مسبقًا.
 */
class RegisterUser
{
    private const DECAY_SECONDS = 3600;

    /**
     * يستهلك محاولة تسجيل من حدّ الـ IP. كل محاولة تُحتسب، ناجحة كانت أو فاشلة.
     *
     * @throws ValidationException
     */
    public function consumeAttempt(string $ip): void
    {
        $key = $this->throttleKey($ip);

        if (RateLimiter::tooManyAttempts($key, (int) config('security.register.max_per_ip_per_hour'))) {
            throw ValidationException::withMessages([
                'form' => __('auth.register_throttled', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    /**
     * @param  string  $phone  رقم مطبَّع بصيغة +9665XXXXXXXX
     *
     * @throws ValidationException
     */
    public function handle(string $fullName, string $phone, string $password, string $ip): User
    {
        try {
            $user = User::query()->create([
                'full_name' => preg_replace('/\s+/u', ' ', trim($fullName)) ?? trim($fullName),
                'phone' => $phone,
                'password' => $password,
                'role' => UserRole::User,
                'is_active' => true,
                'registered_ip' => $ip,
                'last_login_ip' => $ip,
                'last_login_at' => now(),
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['phone' => __('auth.phone_taken')]);
        }

        return $user;
    }

    private function throttleKey(string $ip): string
    {
        return 'register:ip:'.$ip;
    }
}
