<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\MenuRevision;
use App\Models\User;
use App\PermissionKey;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * استرجاع نسخة سابقة من القائمتين: تُنسخ إلى نسخة جديدة في آخر السجل ولا يُحذف
 * شيء، فيمكن التراجع عن الاسترجاع نفسه. يُسجَّل في audit_logs.
 */
class RestoreMenuRevision
{
    public const AUDIT_ACTION = 'menu.revision_restored';

    public function __construct(private readonly ValidateMenuItems $validateMenuItems) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, MenuRevision $revision): MenuRevision
    {
        Gate::forUser($actor)->authorize(PermissionKey::ContentManage->value);

        $items = $this->validateMenuItems->handle(ValidateMenuItems::group($revision->items));

        return DB::transaction(function () use ($actor, $revision, $items): MenuRevision {
            $restored = SaveMenus::apply($items, $actor);

            Audit::record(self::AUDIT_ACTION, $restored, ['from_revision_id' => $revision->getKey()], $actor);

            return $restored;
        });
    }
}
