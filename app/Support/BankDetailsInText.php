<?php

declare(strict_types=1);

namespace App\Support;

/**
 * كشف بيانات حساب بنكي داخل نص حر (docs/SPEC.md FR-53، .cursor/rules/30-security-privacy):
 * الحساب يُعرض من قاعدة المستفيدين فقط، ولا يُكتب في محتوى الصفحات أبدًا.
 *
 * - آيبان سعودي: SA يليه 22 رقمًا، ولو فُصل بمسافات أو شرطات أو نقاط، أو كُتب
 *   بحروف صغيرة أو أرقام عربية، أو قُطِّع بوسوم HTML.
 * - رقم حساب: 10 أرقام متتالية فأكثر (ولو فُصلت بمسافات أو شرطات أو نقاط)، بعد
 *   استبعاد أرقام الجوال السعودية لأنها مسموحة في المحتوى (tel: وwa.me).
 */
final class BankDetailsInText
{
    public const int ACCOUNT_MIN_DIGITS = 10;

    private function __construct()
    {
        //
    }

    public static function containsIban(string $text): bool
    {
        foreach (self::variants($text) as $variant) {
            $compact = preg_replace('/[\s\-.]+/u', '', SaudiIban::normalize($variant)) ?? '';

            if (preg_match('/SA\d{22}/', $compact) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function containsAccountNumber(string $text): bool
    {
        foreach (self::variants($text) as $variant) {
            $digits = SaudiPhone::toWesternDigits($variant);
            $withoutPhones = preg_replace(SaudiPhone::IN_TEXT_PATTERN, ' | ', $digits) ?? $digits;

            if (preg_match('/\d(?:[\s\-.]*\d){'.(self::ACCOUNT_MIN_DIGITS - 1).',}/u', $withoutPhones) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * النص بعد إزالة الوسوم (فلا يُخفى رقم بتقطيعه بوسوم)، والنص كاملًا بسماته
     * (فلا يُخفى في رابط href)، وكلاهما بعد فك الكيانات.
     *
     * @return array{string, string}
     */
    private static function variants(string $text): array
    {
        $withoutTags = preg_replace('/<[^>]*>/', ' ', $text) ?? $text;

        return [
            html_entity_decode($withoutTags, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
        ];
    }
}
