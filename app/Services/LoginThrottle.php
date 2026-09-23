<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

/**
 * القفل المؤقت للدخول (docs/SPEC.md §3, §12.2): بعد عدد من المحاولات الفاشلة
 * خلال نافذة زمنية، لكل جوال ولكل IP على حدة، يُقفل الدخول مدةً تتضاعف مع
 * تكرار القفل. الحدود في config/security.php.
 */
class LoginThrottle
{
    /**
     * الثواني المتبقية على القفل (0 إن لم يكن مقفلًا) للجوال أو للـ IP.
     */
    public function lockedSeconds(string $phone, string $ip): int
    {
        return max(
            $this->remainingLockSeconds($this->phoneKey($phone)),
            $this->remainingLockSeconds($this->ipKey($ip)),
        );
    }

    public function recordFailure(string $phone, string $ip): void
    {
        $maxAttempts = (int) config('security.login.max_attempts');
        $decaySeconds = (int) config('security.login.decay_minutes') * 60;

        foreach ([$this->phoneKey($phone), $this->ipKey($ip)] as $key) {
            $attempts = RateLimiter::hit($key, $decaySeconds);

            if ($attempts >= $maxAttempts) {
                RateLimiter::clear($key);
                $this->lock($key);
            }
        }
    }

    /**
     * بعد دخول ناجح: تصفير محاولات الجوال وتكرار قفله. عدّاد الـ IP لا يُصفَّر
     * كي لا يُستغل الدخول بحساب آخر لتخطي حد الـ IP.
     */
    public function clearPhone(string $phone): void
    {
        $key = $this->phoneKey($phone);

        RateLimiter::clear($key);
        Cache::forget($this->strikesKey($key));
    }

    private function lock(string $key): void
    {
        $strikes = (int) Cache::get($this->strikesKey($key), 0) + 1;

        Cache::put(
            $this->strikesKey($key),
            $strikes,
            now()->addHours((int) config('security.login.strikes_reset_hours')),
        );

        $minutes = min(
            (int) config('security.login.lockout_minutes') * (2 ** ($strikes - 1)),
            (int) config('security.login.lockout_max_minutes'),
        );

        $lockedUntil = now()->addMinutes($minutes);

        Cache::put($this->lockKey($key), $lockedUntil->getTimestamp(), $lockedUntil);
    }

    private function remainingLockSeconds(string $key): int
    {
        $lockedUntil = Cache::get($this->lockKey($key));

        if (! is_int($lockedUntil)) {
            return 0;
        }

        return max(0, $lockedUntil - now()->getTimestamp());
    }

    private function phoneKey(string $phone): string
    {
        return 'login:phone:'.hash('sha256', $phone);
    }

    private function ipKey(string $ip): string
    {
        return 'login:ip:'.$ip;
    }

    private function lockKey(string $key): string
    {
        return $key.':lock';
    }

    private function strikesKey(string $key): string
    {
        return $key.':strikes';
    }
}
