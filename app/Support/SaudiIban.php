<?php

declare(strict_types=1);

namespace App\Support;

/**
 * الآيبان السعودي (docs/SPEC.md §9, §12.10): `SA` ثم 22 رقمًا (24 خانة)، أولها
 * رقما التحقق ثم رمز المصرف (رقمان) ثم 18 رقمًا للحساب، وخانة التحقق وفق
 * ISO 13616 (mod 97 = 1).
 *
 * يقبل الإدخال بمسافات وبحروف صغيرة وبالأرقام العربية (الهندية)، ويُخزَّن مضغوطًا بحروف كبيرة.
 */
final class SaudiIban
{
    public const LENGTH = 24;

    private function __construct()
    {
        //
    }

    /**
     * يزيل المسافات ويوحّد الحروف والأرقام، دون أن يحكم على الصحة.
     */
    public static function normalize(?string $raw): string
    {
        $value = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            (string) $raw,
        );

        return strtoupper((string) preg_replace('/\s+/u', '', $value));
    }

    /**
     * هل الصيغة `SA` + 22 رقمًا؟ لا يفحص خانة التحقق.
     */
    public static function hasValidFormat(?string $raw): bool
    {
        return preg_match('/^SA\d{22}$/', self::normalize($raw)) === 1;
    }

    /**
     * هل الصيغة صحيحة وخانة التحقق سليمة؟
     */
    public static function isValid(?string $raw): bool
    {
        $iban = self::normalize($raw);

        return self::hasValidFormat($iban) && self::mod97(substr($iban, 4).substr($iban, 0, 4)) === 1;
    }

    /**
     * رمز المصرف (الخانتان 5 و6)، أو null إن كانت الصيغة غير صحيحة.
     */
    public static function bankCode(?string $raw): ?string
    {
        $iban = self::normalize($raw);

        return self::hasValidFormat($iban) ? substr($iban, 4, 2) : null;
    }

    /**
     * يبني آيبان صحيحًا من رمز المصرف و18 رقمًا للحساب (للمصانع والاختبارات).
     */
    public static function fromParts(string $bankCode, string $account): string
    {
        $bban = $bankCode.$account;
        $checkDigits = 98 - self::mod97($bban.'SA00');

        return 'SA'.str_pad((string) $checkDigits, 2, '0', STR_PAD_LEFT).$bban;
    }

    /**
     * باقي القسمة على 97 بعد تحويل الحروف إلى أرقام (A=10 ... Z=35).
     */
    private static function mod97(string $value): int
    {
        $remainder = 0;

        foreach (str_split($value) as $character) {
            $digits = ctype_alpha($character) ? (string) (ord($character) - 55) : $character;

            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder;
    }
}
