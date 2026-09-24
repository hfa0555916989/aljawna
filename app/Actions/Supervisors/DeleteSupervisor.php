<?php

declare(strict_types=1);

namespace App\Actions\Supervisors;

use App\Models\User;
use App\PermissionKey;
use App\Services\Audit;
use App\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

/**
 * حذف مشرف (FR-20). الحسابات المرتبطة بسجلات أخرى تبقى وتُرفض الحذف.
 */
class DeleteSupervisor
{
    public const AUDIT_ACTION = 'supervisor.deleted';

    /**
     * @throws AuthorizationException
     * @throws QueryException
     */
    public function handle(User $actor, User $supervisor): void
    {
        if (! $actor->can(PermissionKey::SupervisorsManage->value)) {
            throw new AuthorizationException(__('permissions.errors.unauthorized'));
        }

        if ($actor->is($supervisor)) {
            throw new AuthorizationException(__('permissions.errors.self'));
        }

        if ($supervisor->role !== UserRole::Supervisor) {
            throw new AuthorizationException(__('permissions.errors.not_supervisor'));
        }

        Audit::record(self::AUDIT_ACTION, $supervisor, [], $actor);

        $supervisor->delete();
    }
}
