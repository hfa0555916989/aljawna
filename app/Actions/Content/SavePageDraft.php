<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * حفظ مسودة صفحة (docs/SPEC.md FR-52): لا يغيّر ما يراه الزوار حتى النشر،
 * لأن العرض العام يقرأ آخر نسخة منشورة لا المسودة. يُسجَّل في audit_logs.
 */
class SavePageDraft
{
    public const AUDIT_ACTION = 'page.draft_saved';

    public function __construct(private readonly ValidatePage $validatePage) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Page $page, array $data): Page
    {
        Gate::forUser($actor)->authorize('update', $page);

        $validated = $this->validatePage->handle($data, $page);

        return DB::transaction(function () use ($actor, $page, $validated): Page {
            $page->fill($validated)->save();

            Audit::record(self::AUDIT_ACTION, $page, ['slug' => $page->slug], $actor);

            return $page;
        });
    }
}
