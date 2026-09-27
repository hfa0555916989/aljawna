<?php

declare(strict_types=1);

namespace App\Support;

/**
 * الروابط المسموحة في المحتوى والقوائم (docs/SPEC.md §9 menu_items، .cursor/rules/30-security-privacy):
 * http و https (ومنها https://wa.me/...) و tel فقط، إضافة إلى مسار داخلي في الموقع
 * نفسه يبدأ بـ "/" (مثل /beneficiaries) لأن روابط الأقسام النظامية تحتاجه.
 *
 * يُرفض كل ما سواها: javascript: و data: و mailto: والمسارات التي تبدأ بـ "//"
 * أو فيها مسافات أو محارف تحكّم أو علامات اقتباس أو أقواس زاوية.
 */
final class SafeLink
{
    public const int MAX_LENGTH = 500;

    private function __construct()
    {
        //
    }

    public static function isAllowed(string $url): bool
    {
        if ($url === '' || strlen($url) > self::MAX_LENGTH || preg_match('/[\s\x00-\x1F\x7F\\\\"\'<>`]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return preg_match('~^/(?!/)[A-Za-z0-9\-._\~/?#=&%+]*$~', $url) === 1;
        }

        if (preg_match('/^tel:\+?\d[\d\-]{2,19}$/i', $url) === 1) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && filled(parse_url($url, PHP_URL_HOST));
    }

    /**
     * هل يفتح الرابط خارج الموقع؟ (يُضاف له rel="noopener noreferrer" عند العرض).
     */
    public static function isExternal(string $url): bool
    {
        return preg_match('~^https?://~i', $url) === 1;
    }
}
