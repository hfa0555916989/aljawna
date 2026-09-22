<?php

declare(strict_types=1);

namespace App\Support;

/**
 * تطبيع رقم الجوال السعودي إلى صيغة موحّدة (docs/SPEC.md §3, §9).
 *
 * يقبل الصيغ: `05XXXXXXXX`، `+9665XXXXXXXX`، `009665XXXXXXXX`، بمسافات أو
 * رموز فاصلة بينها، وبالأرقام العربية (الهندية) أو اللاتينية. يُخرج دائمًا
 * `+9665XXXXXXXX`، ويرفض أي رقم غير سعودي أو غير مطابق لصيغة جوال (يبدأ بـ 5
 * بعد رمز الدولة أو الصفر المحلي).
 */
final class SaudiPhone
{
    /**
     * لا يُنشأ ككائن؛ كل الوظائف ساكنة.
     */
    private function __construct()
    {
        //
    }

    /**
     * يطبّع رقم جوال سعودي إلى صيغة `+9665XXXXXXXX`، أو يعيد null إن كان غير صالح.
     */
    public static function normalize(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        $digits = self::toWesternDigits($raw);

        // إزالة كل ما ليس رقمًا أو علامة + في البداية.
        $hasLeadingPlus = str_starts_with(ltrim($digits), '+');
        $digitsOnly = preg_replace('/\D/', '', $digits) ?? '';

        if ($digitsOnly === '') {
            return null;
        }

        $local = match (true) {
            $hasLeadingPlus && str_starts_with($digitsOnly, '966') => substr($digitsOnly, 3),
            ! $hasLeadingPlus && str_starts_with($digitsOnly, '00966') => substr($digitsOnly, 5),
            ! $hasLeadingPlus && str_starts_with($digitsOnly, '0') && ! str_starts_with($digitsOnly, '00') => substr($digitsOnly, 1),
            default => null,
        };

        if ($local === null || preg_match('/^5\d{8}$/', $local) !== 1) {
            return null;
        }

        return '+966'.$local;
    }

    /**
     * هل الرقم المُدخل صيغة جوال سعودية صالحة (بعد التطبيع)؟
     */
    public static function isValid(?string $raw): bool
    {
        return self::normalize($raw) !== null;
    }

    /**
     * يحوّل الأرقام العربية (الهندية) ٠-٩ إلى أرقام لاتينية 0-9.
     */
    private static function toWesternDigits(string $value): string
    {
        $arabicIndic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $western = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

        return str_replace($arabicIndic, $western, $value);
    }
}
