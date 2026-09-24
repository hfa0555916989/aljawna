<?php

declare(strict_types=1);

namespace App\Support;

/**
 * تطبيع مبلغ يُدخله المستخدم إلى نص عشري بخانتين يناسب decimal(12,2) دون المرور بـ float.
 *
 * يقبل الأرقام العربية (الهندية)، والفاصلة العشرية العربية «٫»، وفواصل الآلاف «,» و«٬» والمسافات.
 * يرفض السالب والصفر وأكثر من خانتين عشريتين وما يتجاوز 10 خانات صحيحة.
 */
final class AmountInput
{
    private const INTEGER_DIGITS = 10;

    private function __construct()
    {
        //
    }

    /**
     * @return numeric-string|null
     */
    public static function normalize(?string $raw): ?string
    {
        $value = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩', '٫', '٬', ','],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '.', '', ''],
            (string) $raw,
        );
        $value = (string) preg_replace('/\s+/u', '', $value);

        if (preg_match('/^\d{1,'.self::INTEGER_DIGITS.'}(\.\d{1,2})?$/', $value) !== 1 || ! is_numeric($value)) {
            return null;
        }

        $amount = bcadd($value, '0', 2);

        return bccomp($amount, '0', 2) === 1 ? $amount : null;
    }
}
