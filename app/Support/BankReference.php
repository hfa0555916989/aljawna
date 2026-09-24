<?php

declare(strict_types=1);

namespace App\Support;

/**
 * رقم العملية المصرفية الذي يُدخله المبادر اختياريًا (docs/SPEC.md §9 transfers, FR-17).
 *
 * يُطبَّع ليُقارَن بدقة عند كشف المتكرر: أرقام لاتينية وحروف لاتينية كبيرة وشرطات، بلا مسافات.
 */
final class BankReference
{
    public const MAX_LENGTH = 64;

    private function __construct()
    {
        //
    }

    /**
     * يعيد null للقيمة الفارغة.
     */
    public static function normalize(?string $raw): ?string
    {
        $value = str_replace(
            ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'],
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            (string) $raw,
        );
        $value = strtoupper((string) preg_replace('/\s+/u', '', $value));

        return $value === '' ? null : $value;
    }

    public static function isValid(?string $raw): bool
    {
        $value = self::normalize($raw);

        return $value === null
            || preg_match('/^[A-Z0-9][A-Z0-9\-]{0,'.(self::MAX_LENGTH - 1).'}$/', $value) === 1;
    }
}
