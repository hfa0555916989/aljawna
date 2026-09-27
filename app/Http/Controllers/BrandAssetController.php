<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SiteBranding;
use Illuminate\Http\Response;

/**
 * يقدّم ملفات SVG الشعار المعتمدة من resources/images/brand (docs/SPEC.md FR-49).
 *
 * قائمة مغلقة بأسماء الملفات المعتمدة فقط (لا مدخل مستخدم حر في المسار)، وتُخدم
 * من نطاق الموقع نفسه بترويسة تخزين مؤقت طويلة لأن الملفات لا تتغيّر.
 */
class BrandAssetController extends Controller
{
    /** @var list<string> */
    private const ALLOWED = [
        SiteBranding::DEFAULT_LIGHT_LOGO_ASSET,
        SiteBranding::DEFAULT_DARK_LOGO_ASSET,
        SiteBranding::DEFAULT_ICON_ASSET,
    ];

    public function __invoke(string $asset): Response
    {
        abort_unless(in_array($asset, self::ALLOWED, true), 404);

        $path = resource_path('images/brand/'.$asset);

        abort_unless(is_file($path), 404);

        return response(
            (string) file_get_contents($path),
            200,
            [
                'Content-Type' => 'image/svg+xml',
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'public, max-age=31536000, immutable',
            ],
        );
    }
}
