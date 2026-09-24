<?php

declare(strict_types=1);

namespace App\Actions\Supervisors;

use App\Models\User;
use App\PermissionKey;
use App\Services\Audit;
use App\UserRole;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * تعطيل مشرف أو تفعيله. يسري على الطلب التالي لأن الدخول واللوحة يفحصان is_active.
 */
class SetSupervisorActive
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $supervisor, bool $active): void
    {
        $this->authorize($actor, $supervisor);

        if ($supervisor->is_active === $active) {
            return;
        }

        $supervisor->forceFill(['is_active' => $active])->save();

        Audit::record($active ? 'supervisor.activated' : 'supervisor.deactivated', $supervisor, [], $actor);
    }

    /**
     * @throws AuthorizationException
     */
    private function authorize(User $actor, User $supervisor): void
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
    }
}
