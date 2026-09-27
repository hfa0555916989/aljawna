<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\MenuRevision;
use App\Models\User;
use App\PermissionKey;
use App\Services\Audit;
use App\Services\SiteMenu;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * حفظ قائمتي الرأس والتذييل معًا (docs/SPEC.md FR-54): تُستبدل العناصر وتُكتب نسخة
 * جديدة في menu_revisions ليمكن استرجاع أي نسخة سابقة. يُسجَّل في audit_logs.
 */
class SaveMenus
{
    public const AUDIT_ACTION = 'menu.updated';

    public function __construct(private readonly ValidateMenuItems $validateMenuItems) {}

    /**
     * @param  array<array-key, mixed>  $menus
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, array $menus): MenuRevision
    {
        Gate::forUser($actor)->authorize(PermissionKey::ContentManage->value);

        $items = $this->validateMenuItems->handle($menus);

        return DB::transaction(function () use ($actor, $items): MenuRevision {
            $revision = self::apply($items, $actor);

            Audit::record(self::AUDIT_ACTION, $revision, ['items_count' => count($items)], $actor);

            return $revision;
        });
    }

    /**
     * يستبدل العناصر ويكتب نسخة جديدة (يُستدعى داخل معاملة).
     *
     * @param  list<array{location: string, label: string, page_id: int|null, url: string|null}>  $items
     */
    public static function apply(array $items, User $actor): MenuRevision
    {
        SiteMenu::replace($items);

        return MenuRevision::query()->create(['items' => $items, 'author_id' => $actor->getKey()]);
    }
}
