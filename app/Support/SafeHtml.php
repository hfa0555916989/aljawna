<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * تنظيف النص المنسّق في كتل الصفحات على الخادم بقائمة سماح صارمة (docs/SPEC.md FR-53, §12.13).
 *
 * لا سمات style ولا class ولا أحداث، ولا وسوم script أو iframe أو img أو form؛
 * الروابط http و https و tel ومسارات الموقع النسبية فقط، وتُفرض لها rel آمنة.
 * أما إعداد Filament العام فيسمح بـ style و class، لذلك لا يُستخدم هنا.
 */
final class SafeHtml
{
    public const int MAX_INPUT_LENGTH = 50000;

    private const array ALLOWED_ELEMENTS = [
        'p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h2', 'h3', 'ul', 'ol', 'li', 'blockquote',
    ];

    private const array UNWRAPPED_ELEMENTS = ['div', 'span', 'font', 'section', 'article'];

    private static ?HtmlSanitizer $sanitizer = null;

    private function __construct()
    {
        //
    }

    public static function clean(string $html): string
    {
        return trim(self::sanitizer()->sanitize($html));
    }

    /**
     * النص الظاهر فقط (لحساب الطول وكشف الحسابات البنكية).
     */
    public static function plainText(string $html): string
    {
        $withoutTags = preg_replace('/<[^>]*>/', ' ', $html) ?? $html;
        $decoded = html_entity_decode($withoutTags, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $decoded) ?? $decoded);
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = (new HtmlSanitizerConfig)
            ->allowLinkSchemes(['http', 'https', 'tel'])
            ->allowRelativeLinks()
            ->allowElement('a', ['href'])
            ->forceAttribute('a', 'rel', 'nofollow noopener noreferrer')
            ->withMaxInputLength(self::MAX_INPUT_LENGTH);

        foreach (self::ALLOWED_ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        foreach (self::UNWRAPPED_ELEMENTS as $element) {
            $config = $config->blockElement($element);
        }

        return self::$sanitizer = new HtmlSanitizer($config);
    }
}
