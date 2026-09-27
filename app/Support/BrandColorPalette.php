<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

/**
 * لوحة ألوان الهوية المضبوطة وفحص تباينها (docs/SPEC.md FR-49, config/branding.php).
 *
 * لكل دور (primary أو secondary) مفاتيح محدودة معرّفة في الإعدادات فقط، وكل
 * مفتاح يُفحص مقابل خلفيتَي الوضعين الفاتح والداكن معًا قبل قبوله. primary
 * يُستخدم نصًّا عاديًا وخلفية زر فيتطلب 4.5:1، وsecondary يُستخدم فقط أرقامًا
 * كبيرة عريضة (dashboard.blade.php, home.blade.php) فيكفيه 3:1 (نص كبير AA).
 */
final class BrandColorPalette
{
    public const string ROLE_PRIMARY = 'primary';

    public const string ROLE_SECONDARY = 'secondary';

    public static function threshold(string $role): float
    {
        return $role === self::ROLE_PRIMARY ? ContrastRatio::AA_NORMAL : ContrastRatio::AA_LARGE;
    }

    /**
     * @return array{label: string, light: string, dark: string}|null
     */
    public static function find(string $role, string $key): ?array
    {
        /** @var array{label: string, light: string, dark: string}|null $entry */
        $entry = config("branding.palette.{$role}.{$key}");

        return $entry;
    }

    /**
     * @param  array{label: string, light: string, dark: string}  $entry
     */
    public static function passesContrast(array $entry, string $role): bool
    {
        $minimum = self::threshold($role);

        /** @var array<string, string> $lightBackgrounds */
        $lightBackgrounds = config('branding.backgrounds.light');
        /** @var array<string, string> $darkBackgrounds */
        $darkBackgrounds = config('branding.backgrounds.dark');
        /** @var list<string> $checked */
        $checked = config("branding.checked_backgrounds.{$role}");

        foreach ($checked as $backgroundKey) {
            if (! ContrastRatio::passesAA($entry['light'], $lightBackgrounds[$backgroundKey], $minimum)) {
                return false;
            }

            if (! ContrastRatio::passesAA($entry['dark'], $darkBackgrounds[$backgroundKey], $minimum)) {
                return false;
            }
        }

        return true;
    }

    /**
     * يفحص المفتاح مباشرة: يرفض المفتاح غير المعرَّف أو ضعيف التباين.
     */
    public static function isAccessible(string $role, string $key): bool
    {
        $entry = self::find($role, $key);

        return $entry !== null && self::passesContrast($entry, $role);
    }

    /**
     * المفتاح الافتراضي لدور ما (config/branding.php default_primary أو default_secondary)،
     * مضمون الوجود دائمًا لأنه معرَّف في الإعدادات، بخلاف find() العامة.
     *
     * @return array{label: string, light: string, dark: string}
     */
    public static function defaultEntry(string $role): array
    {
        $key = (string) config($role === self::ROLE_PRIMARY ? 'branding.default_primary' : 'branding.default_secondary');

        return self::find($role, $key) ?? throw new RuntimeException("لوحة ألوان الهوية غير مضبوطة للدور: {$role}");
    }
}
