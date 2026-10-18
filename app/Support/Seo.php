<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\Request;

/**
 * قواعد الفهرسة في محركات البحث (docs/SPEC.md FR-55).
 *
 * النطاق الأساسي = نطاق APP_URL (مثل ajawna.com)، وهو وحده ما يُفهرس ويُشار إليه في
 * canonical. أي نطاق آخر يصل للتطبيق نفسه (نطاق laravel.cloud الافتراضي أو www قبل
 * تحويله) يُمنع من الفهرسة، فلا يظهر محتوى مكرر في نتائج البحث.
 */
final class Seo
{
    /**
     * الصفحات العامة القابلة للفهرسة، وما عداها (الدخول والتسجيل واللوحة والروابط
     * المؤقتة) يحمل noindex.
     *
     * @var list<string>
     */
    public const array INDEXABLE_ROUTES = [
        'home',
        'beneficiaries.index',
        'beneficiaries.show',
        'pages.show',
    ];

    private function __construct()
    {
        //
    }

    public static function baseUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }

    /**
     * رابط الصفحة الحالية على النطاق الأساسي، بلا معاملات الاستعلام.
     */
    public static function canonicalUrl(Request $request): string
    {
        $path = trim($request->path(), '/');

        return $path === '' ? self::baseUrl() : self::baseUrl().'/'.$path;
    }

    public static function isCanonicalHost(Request $request): bool
    {
        $canonicalHost = parse_url(self::baseUrl(), PHP_URL_HOST);

        return is_string($canonicalHost) && strcasecmp($request->getHost(), $canonicalHost) === 0;
    }

    public static function shouldIndex(Request $request): bool
    {
        return self::isCanonicalHost($request) && $request->routeIs(...self::INDEXABLE_ROUTES);
    }

    public static function googleSiteVerification(): ?string
    {
        $token = config('services.google.site_verification');

        return is_string($token) && $token !== '' ? $token : null;
    }
}
