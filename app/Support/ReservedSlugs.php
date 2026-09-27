<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * مسارات الصفحات (slug) ومنع حجزها لمسار نظامي (docs/SPEC.md FR-55).
 *
 * المحجوز = قائمة security.pages.reserved_slugs + مسار لوحة الإدارة (ADMIN_PATH)
 * صراحةً + أول مقطع ثابت من كل مسار مسجّل (ومنه /up و livewire)، فلا يُنشأ مسار نظامي جديد تحجبه صفحة
 * قديمة ولا العكس. ومسار الصفحات GET /{slug} مسجَّل أخيرًا (fallback) احتياطًا.
 */
final class ReservedSlugs
{
    /**
     * حروف لاتينية صغيرة وأرقام وشرطات مفردة بينها، مثل about-us.
     */
    public const string PATTERN = '[a-z0-9]+(?:-[a-z0-9]+)*';

    public const int MAX_LENGTH = 80;

    private function __construct()
    {
        //
    }

    /**
     * قيد مسار GET /{slug}: صيغة المسار مع استبعاد كل مقطع محجوز، فلا يلتقط مسار
     * الصفحات طلبًا لمسار نظامي بطريقة أخرى (مثل GET /logout يبقى 405 لا 404).
     */
    public static function routePattern(): string
    {
        $reserved = implode('|', array_map(fn (string $slug): string => preg_quote($slug, '/'), self::all()));

        return "(?!(?:{$reserved})$)".self::PATTERN;
    }

    public static function isValidFormat(string $slug): bool
    {
        return strlen($slug) <= self::MAX_LENGTH && preg_match('/^'.self::PATTERN.'$/', $slug) === 1;
    }

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::all(), true);
    }

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        /** @var list<string> $configured */
        $configured = config('security.pages.reserved_slugs', []);

        return array_values(array_unique([
            ...$configured,
            strtolower((string) config('admin.path')),
            ...self::routeSegments(),
        ]));
    }

    /**
     * @return list<string>
     */
    public static function routeSegments(): array
    {
        $segments = [];

        /** @var Route $route */
        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if ($route->isFallback) {
                continue;
            }

            $first = strtolower(explode('/', trim($route->uri(), '/'))[0]);

            if ($first !== '' && ! str_contains($first, '{')) {
                $segments[] = pathinfo($first, PATHINFO_FILENAME);
            }
        }

        return array_values(array_unique($segments));
    }
}
