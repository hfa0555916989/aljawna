<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SiteBranding;
use Illuminate\Support\Facades\Cache;

/**
 * حقن ألوان الهوية كمتغيرات CSS (`--pri`, `--brass`) في التخطيط (tasks/T16-branding.md #4).
 *
 * تُخزَّن نتيجة التوليد مؤقتًا بلا انتهاء، وتُبطَل فورًا عند حفظ App\Models\SiteBranding
 * (انظر SiteBranding::booted()). القيم مضمونة سليمة التباين لأنها فُحصت عند الحفظ
 * عبر App\Rules\AccessibleBrandColor، فلا حاجة لفحصها مرة أخرى هنا.
 */
final class BrandingCss
{
    private const string CACHE_KEY = 'branding:css_vars';

    /**
     * كتلة `<style>` جاهزة للحقن في `<head>` (تخطيط الموقع ولوحة الإدارة معًا).
     */
    public static function styleTag(): string
    {
        return '<style>'.self::variables().'</style>';
    }

    public static function variables(): string
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): string => self::build());
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private static function build(): string
    {
        $branding = SiteBranding::current();
        $primary = $branding->primaryColor();
        $secondary = $branding->secondaryColor();

        return sprintf(
            ':root{--pri:%s;--brass:%s}@media (prefers-color-scheme: dark){:root{--pri:%s;--brass:%s}}',
            $primary['light'],
            $secondary['light'],
            $primary['dark'],
            $secondary['dark'],
        );
    }
}
