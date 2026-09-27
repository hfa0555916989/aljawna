<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use Illuminate\Http\Response;

/**
 * خريطة الموقع /sitemap.xml (docs/SPEC.md FR-55): الرئيسية وقائمة المبادرات
 * والصفحات المنشورة فقط، بلا مسودات ولا صفحات الدخول أو اللوحة.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $pages = Page::query()
            ->published()
            ->where('is_system', false)
            ->orderBy('slug')
            ->get(['slug', 'published_at', 'updated_at']);

        return response()
            ->view('sitemap', ['pages' => $pages])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
