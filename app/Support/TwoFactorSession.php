<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * هل اجتازت الجلسة الحالية التحقق بخطوتين؟ (docs/DECISIONS.md)
 *
 * كل دخول (حدث Login) يضع العلامة: "لم تجتز" إن كان صاحب دور اللوحة قد فعّل
 * التحقق، و"مجتازة" إن لم يفعّله بعد (فيُحوَّل إلى صفحة الإعداد الإلزامية).
 * صفحة /login وحدها ترفعها إلى "مجتازة" بعد رمز صحيح. فأي طريق دخول آخر
 * يتجاوز الخطوة الثانية تُرفض جلسته في اللوحة (EnsureTwoFactorAuthentication).
 */
final class TwoFactorSession
{
    public const string SESSION_KEY = 'auth_two_factor_passed';

    private function __construct()
    {
        //
    }

    public static function markOnLogin(User $user): void
    {
        session()->put(self::SESSION_KEY, ! self::isRequiredChallenge($user));
    }

    public static function markPassed(): void
    {
        session()->put(self::SESSION_KEY, true);
    }

    /**
     * جلسة صاحب تحقق مفعَّل دخلت دون اجتياز الخطوة الثانية.
     */
    public static function isUnverified(User $user): bool
    {
        return self::isRequiredChallenge($user) && session()->get(self::SESSION_KEY) === false;
    }

    private static function isRequiredChallenge(User $user): bool
    {
        return $user->hasPanelRole() && $user->hasTwoFactorEnabled();
    }
}
