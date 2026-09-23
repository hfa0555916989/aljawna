<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * قراءة قوالب الأدوار الجاهزة من config/permission_templates.php (docs/SPEC.md §2).
 */
final class PermissionTemplates
{
    /**
     * @return array<string, array{label: string, permissions: list<string>}>
     */
    public static function all(): array
    {
        /** @var array<string, array{label: string, permissions: list<string>}> $templates */
        $templates = config('permission_templates', []);

        return $templates;
    }

    /**
     * @return list<string>
     */
    public static function permissions(string $template): array
    {
        $templates = self::all();

        if (! array_key_exists($template, $templates)) {
            throw new InvalidArgumentException("Unknown permission template [{$template}].");
        }

        return $templates[$template]['permissions'];
    }
}
