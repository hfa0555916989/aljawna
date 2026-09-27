<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\PageStatus;
use App\Services\Audit;
use App\Support\PageBlocks;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * نشر المسودة الحالية (docs/SPEC.md FR-52): يكتب نسخة جديدة في page_revisions
 * تصبح هي ما يراه الزوار. المسودة يُعاد التحقق منها قبل النشر احتياطًا.
 */
class PublishPage
{
    public const AUDIT_ACTION = 'page.published';

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Page $page): PageRevision
    {
        Gate::forUser($actor)->authorize('publish', $page);

        $blocks = PageBlocks::validate($page->blocks);

        return DB::transaction(function () use ($actor, $page, $blocks): PageRevision {
            $revision = $page->revisions()->create(['blocks' => $blocks, 'author_id' => $actor->getKey()]);

            $page->forceFill([
                'blocks' => $blocks,
                'status' => PageStatus::Published,
                'published_at' => now(),
            ])->save();

            $page->unsetRelation('latestRevision');

            Audit::record(self::AUDIT_ACTION, $page, ['slug' => $page->slug, 'revision_id' => $revision->getKey()], $actor);

            return $revision;
        });
    }
}
