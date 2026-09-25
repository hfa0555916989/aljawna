<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * رقم جيل الجلسة لكل مستخدم. رفعه يُنهي الجلسات القديمة لأن وسيط الجلسة
 * يقارنه بما خُزّن عند الدخول (docs/SPEC.md §4.2).
 */
final class SessionEpoch
{
    public const string SESSION_KEY = 'auth_session_epoch';

    public static function current(int $userId): int
    {
        return (int) Cache::get(self::cacheKey($userId), 0);
    }

    public static function bump(int $userId): void
    {
        Cache::forever(self::cacheKey($userId), self::current($userId) + 1);
    }

    public static function bindToSession(int $userId): void
    {
        session()->put(self::SESSION_KEY, self::current($userId));
    }

    private static function cacheKey(int $userId): string
    {
        return 'auth.session_epoch.'.$userId;
    }
}
