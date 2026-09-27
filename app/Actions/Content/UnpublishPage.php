<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use App\Models\User;
use App\PageStatus;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * إلغاء نشر صفحة فتعود مسودة وتختفي من الموقع وخريطته (docs/SPEC.md FR-52).
 * نسخها تبقى كما هي، والصفحة النظامية (الرئيسية) لا يُلغى نشرها (PagePolicy).
 */
class UnpublishPage
{
    public const AUDIT_ACTION = 'page.unpublished';

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Page $page): Page
    {
        Gate::forUser($actor)->authorize('unpublish', $page);

        return DB::transaction(function () use ($actor, $page): Page {
            $page->forceFill(['status' => PageStatus::Draft])->save();

            Audit::record(self::AUDIT_ACTION, $page, ['slug' => $page->slug], $actor);

            return $page;
        });
    }
}
