<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * استرجاع نسخة سابقة (docs/SPEC.md FR-52): تُنسخ كتلها إلى نسخة جديدة في آخر
 * السجل وإلى المسودة، فلا تُحذف أي نسخة ويمكن التراجع عن الاسترجاع نفسه.
 * للصفحة المنشورة تصبح النسخة الجديدة هي المعروضة فورًا.
 */
class RestorePageRevision
{
    public const AUDIT_ACTION = 'page.revision_restored';

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Page $page, PageRevision $revision): PageRevision
    {
        Gate::forUser($actor)->authorize('restoreRevision', $page);

        if ($revision->page_id !== $page->getKey()) {
            throw new AuthorizationException(__('pages.errors.foreign_revision'));
        }

        return DB::transaction(function () use ($actor, $page, $revision): PageRevision {
            $restored = $page->revisions()->create(['blocks' => $revision->blocks, 'author_id' => $actor->getKey()]);

            $page->forceFill(['blocks' => $revision->blocks])->save();
            $page->unsetRelation('latestRevision');

            Audit::record(self::AUDIT_ACTION, $page, [
                'slug' => $page->slug,
                'from_revision_id' => $revision->getKey(),
                'revision_id' => $restored->getKey(),
            ], $actor);

            return $restored;
        });
    }
}
