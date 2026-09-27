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
use Illuminate\Validation\ValidationException;

/**
 * إنشاء صفحة جديدة كمسودة غير منشورة (docs/SPEC.md FR-50, FR-52)، ضمن حد
 * عدد الصفحات (§12.13). تُسجَّل في audit_logs.
 */
class CreatePage
{
    public const AUDIT_ACTION = 'page.created';

    public function __construct(private readonly ValidatePage $validatePage) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $data): Page
    {
        Gate::forUser($actor)->authorize('create', Page::class);

        $maxPages = (int) config('security.pages.max_pages');

        if (Page::query()->count() >= $maxPages) {
            throw ValidationException::withMessages(['title' => __('pages.validation.too_many_pages', ['max' => $maxPages])]);
        }

        $validated = $this->validatePage->handle($data);

        return DB::transaction(function () use ($actor, $validated): Page {
            $page = new Page($validated);
            $page->forceFill(['status' => PageStatus::Draft, 'is_system' => false])->save();

            Audit::record(self::AUDIT_ACTION, $page, ['slug' => $page->slug], $actor);

            return $page;
        });
    }
}
