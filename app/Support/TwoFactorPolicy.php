<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\Rules\Password;

/**
 * هل التحقق بخطوتين إلزامي لأدوار اللوحة؟ (TWO_FACTOR_REQUIRED، docs/DECISIONS.md)
 *
 * true (الافتراضي): السلوك الأصلي كما هو، إعداد TOTP في رابط الدعوة وخطوة ثانية عند الدخول.
 * false: الدعوة ورابط الاستعادة يطلبان تأكيد رقم الجوال وكلمة مرور فقط، والدخول
 * بالجوال وكلمة المرور، ولا يُطلب رمز من أي حساب ولو كان قد فعّله سابقًا.
 */
final class TwoFactorPolicy
{
    private function __construct()
    {
        //
    }

    public static function isRequired(): bool
    {
        return (bool) config('security.two_factor.required', true);
    }

    /**
     * صلاحية روابط الدعوة والاستعادة في الوضع المبسّط.
     */
    public static function simpleLinkHours(): int
    {
        return (int) config('security.two_factor.simple_link_hours', 48);
    }

    /**
     * محاولات تأكيد رقم جوال خاطئة على الرابط نفسه قبل إلغائه.
     */
    public static function maxPhoneAttempts(): int
    {
        return (int) config('security.two_factor.max_phone_attempts', 5);
    }

    /**
     * كلمة مرور المدير والمشرف في الوضع المبسّط: 8 على الأقل بحروف وأرقام ورموز.
     */
    public static function simplePasswordRule(): Password
    {
        return Password::min(8)->letters()->numbers()->symbols();
    }
}
