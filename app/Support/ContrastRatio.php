<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نسبة تباين WCAG 2.1 بين لونين (docs/SPEC.md FR-49, §12.13).
 *
 * الحسابات على السطوع النسبي (relative luminance) بصيغة WCAG القياسية.
 * AA_NORMAL هو حد النص العادي (4.5:1)، وAA_LARGE حد النص الكبير أو العريض
 * (3:1؛ ≥ 24px عريض أو ≥ 32px عادي)، المطبَّق فعليًا على "brass" في هذا التطبيق
 * (أرقام إحصائية كبيرة عريضة في resources/views/livewire/dashboard.blade.php
 * وhome.blade.php)، بخلاف "pri" الذي يُستخدم أيضًا نصًّا عاديًا فيتطلب 4.5:1.
 */
final class ContrastRatio
{
    public const float AA_NORMAL = 4.5;

    public const float AA_LARGE = 3.0;

    /**
     * نسبة التباين بين لونين (أكبر من 1، الأعلى أوضح).
     */
    public static function of(string $hexA, string $hexB): float
    {
        $lighter = max(self::luminance($hexA), self::luminance($hexB));
        $darker = min(self::luminance($hexA), self::luminance($hexB));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    public static function passesAA(string $foreground, string $background, float $minimum = self::AA_NORMAL): bool
    {
        return self::of($foreground, $background) >= $minimum;
    }

    private static function luminance(string $hex): float
    {
        $hex = ltrim($hex, '#');
        $r = (int) hexdec(substr($hex, 0, 2));
        $g = (int) hexdec(substr($hex, 2, 2));
        $b = (int) hexdec(substr($hex, 4, 2));

        return 0.2126 * self::channel($r) + 0.7152 * self::channel($g) + 0.0722 * self::channel($b);
    }

    private static function channel(int $value): float
    {
        $normalized = $value / 255;

        return $normalized <= 0.03928 ? $normalized / 12.92 : (($normalized + 0.055) / 1.055) ** 2.4;
    }
}
