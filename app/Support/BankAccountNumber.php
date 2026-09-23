<?php

declare(strict_types=1);

namespace App\Support;

/**
 * رقم الحساب البنكي للمستفيد (docs/SPEC.md §9, §12.10): أرقام فقط.
 * يقبل الأرقام العربية (الهندية) والمسافات عند الإدخال، ويُخزَّن أرقامًا لاتينية متصلة.
 */
final class BankAccountNumber
{
    public const MAX_LENGTH = 30;

    private function __construct()
    {
        //
    }

    public static function normalize(?string $raw): string
    {
        $value = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            (string) $raw,
        );

        return (string) preg_replace('/\s+/u', '', $value);
    }

    public static function isValid(?string $raw): bool
    {
        return preg_match('/^\d{1,'.self::MAX_LENGTH.'}$/', self::normalize($raw)) === 1;
    }
}
