<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

/**
 * هل اجتازت الجلسة الحالية التحقق بخطوتين؟ (docs/DECISIONS.md)
 *
 * كل دخول (حدث Login) يضع العلامة: "لم تجتز" إن كان صاحب دور اللوحة قد فعّل
 * التحقق. ترفعها إلى "مجتازة" صفحة /login بعد رمز صحيح، وصفحات إعداد التحقق
 * (الدعوات ورابط admin:reset-2fa) بعد تفعيله برمز صحيح. فأي طريق دخول آخر
 * يتجاوز الخطوة الثانية، أو جلسة دور لوحة بلا تحقق مفعَّل، تُنهى في كل مسار
 * (EnsurePanelRoleTwoFactor وEnsureTwoFactorAuthentication).
 *
 * الجلسة بلا علامة أصلًا (لم تمرّ بحدث Login، كما في actingAs بالاختبارات) لا
 * تُعدّ "لم تجتز"، لأن كل دخول حقيقي يمرّ بالحدث، ومنه الدخول بـ "تذكّرني".
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
     * هل يجوز لهذه الجلسة أي وصول؟ المبادر دائمًا. دور اللوحة بشرط تحقق مفعَّل
     * وجلسة لم تُعلَّم "لم تجتز" (docs/DECISIONS.md، T20)، ما لم يكن التحقق غير
     * إلزامي (TWO_FACTOR_REQUIRED=false) فتكفي كلمة المرور.
     */
    public static function allowsAccess(User $user): bool
    {
        if (! $user->hasPanelRole() || ! TwoFactorPolicy::isRequired()) {
            return true;
        }

        return $user->hasTwoFactorEnabled() && ! self::isUnverified($user);
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
        return TwoFactorPolicy::isRequired() && $user->hasPanelRole() && $user->hasTwoFactorEnabled();
    }
}
