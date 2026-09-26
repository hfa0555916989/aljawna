<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * جيل جلسات المستخدم في عمود users.session_epoch. رفعه ينهي كل جلساته القائمة
 * في الموقع ولوحة الإدارة عند تعديل رقم الدخول (docs/SPEC.md §4.2).
 */
final class SessionEpoch
{
    public const string SESSION_KEY = 'auth_session_epoch';

    public static function current(User $user): int
    {
        if (array_key_exists('session_epoch', $user->getAttributes())) {
            return (int) $user->session_epoch;
        }

        return (int) User::query()->whereKey($user->getKey())->value('session_epoch');
    }

    /**
     * رفع ذري في قاعدة البيانات، مع تدوير رمز "تذكّرني" كي لا تعود جلسة قديمة عبره.
     */
    public static function bump(int $userId): void
    {
        User::query()->whereKey($userId)->toBase()->update([
            'session_epoch' => DB::raw('session_epoch + 1'),
            'remember_token' => Str::random(60),
        ]);
    }

    public static function bindToSession(User $user): void
    {
        session()->put(self::SESSION_KEY, self::current($user));
    }
}
