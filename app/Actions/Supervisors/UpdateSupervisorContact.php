<?php

declare(strict_types=1);

namespace App\Actions\Supervisors;

use App\Models\User;
use App\PermissionKey;
use App\Services\Audit;
use App\UserRole;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * خيار ظهور رقم المشرف عند الاستعادة (FR-21).
 */
class UpdateSupervisorContact
{
    public const AUDIT_ACTION = 'supervisor.contact_updated';

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, User $supervisor, bool $showContact): void
    {
        if (! $actor->can(PermissionKey::SupervisorsManage->value)) {
            throw new AuthorizationException(__('permissions.errors.unauthorized'));
        }

        if ($supervisor->role !== UserRole::Supervisor) {
            throw new AuthorizationException(__('permissions.errors.not_supervisor'));
        }

        if ($supervisor->show_contact === $showContact) {
            return;
        }

        $supervisor->forceFill(['show_contact' => $showContact])->save();

        Audit::record(self::AUDIT_ACTION, $supervisor, [
            'show_contact' => $showContact,
        ], $actor);
    }
}
