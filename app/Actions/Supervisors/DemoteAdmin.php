<?php

declare(strict_types=1);

namespace App\Actions\Supervisors;

use App\Models\User;
use App\Services\Audit;
use App\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * تخفيض دور مدير. يملكه مدير فعّال آخر فقط، لا المدير نفسه ولا أي مشرف مهما
 * كانت صلاحياته، ويبقى مدير فعّال واحد على الأقل (حارس نموذج User).
 */
class DemoteAdmin
{
    public const AUDIT_ACTION = 'admin.demoted';

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $admin, UserRole $newRole = UserRole::Supervisor): void
    {
        if ($newRole === UserRole::Admin) {
            throw new InvalidArgumentException('The new role must not be admin.');
        }

        if (! $actor->isActiveAdmin()) {
            throw new AuthorizationException(__('permissions.errors.demote_admin_only'));
        }

        if ($actor->is($admin)) {
            throw new AuthorizationException(__('permissions.errors.demote_self'));
        }

        if ($admin->role !== UserRole::Admin) {
            throw new AuthorizationException(__('permissions.errors.demote_not_admin'));
        }

        DB::transaction(function () use ($actor, $admin, $newRole): void {
            $admin->update(['role' => $newRole]);

            Audit::record(self::AUDIT_ACTION, $admin, [
                'from' => UserRole::Admin->value,
                'to' => $newRole->value,
            ], $actor);
        });
    }
}
