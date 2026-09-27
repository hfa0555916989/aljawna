<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\PageRenderer;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * معاينة المسودة الحالية قبل النشر (docs/SPEC.md FR-52) لمن يملك content.manage.
 * لا تكتب شيئًا ولا تنشر، ولا تُفهرس ولا تُخزَّن مؤقتًا.
 */
class PreviewPageController extends Controller
{
    public function __invoke(Page $page, PageRenderer $renderer): Response
    {
        Gate::authorize('update', $page);

        return response()
            ->view('pages.show', [
                'page' => $page,
                'blocks' => $renderer->prepare($page->blocks),
                'isPreview' => true,
            ])
            ->header('Cache-Control', 'no-store, private')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
