<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Page;
use App\Services\PageRenderer;
use Illuminate\Contracts\View\View;

/**
 * صفحة عامة من منشئ الصفحات GET /{slug} (docs/SPEC.md FR-51, FR-55).
 *
 * المسار مسجَّل أخيرًا (fallback) فلا يحجب مسارًا نظاميًا. تُعرض آخر نسخة منشورة
 * فقط، والمسودات والصفحة النظامية (الرئيسية، ومسارها /) ليست هنا: 404.
 */
class ShowPageController extends Controller
{
    public function __invoke(string $slug, PageRenderer $renderer): View
    {
        $page = Page::query()
            ->published()
            ->where('is_system', false)
            ->where('slug', $slug)
            ->with('latestRevision')
            ->first();

        $blocks = $page?->liveBlocks();

        abort_if($page === null || $blocks === null, 404);

        return view('pages.show', [
            'page' => $page,
            'blocks' => $renderer->prepare($blocks),
            'isPreview' => false,
        ]);
    }
}
