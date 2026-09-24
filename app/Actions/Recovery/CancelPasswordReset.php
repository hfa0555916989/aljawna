<?php

declare(strict_types=1);

namespace App\Actions\Recovery;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\PasswordResetStatus;
use App\PermissionKey;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * إلغاء طلب استعادة (FR-9). الحجز القائم لمشرف آخر لا يُلغى.
 */
class CancelPasswordReset
{
    public const AUDIT_ACTION = 'recovery.cancelled';

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, PasswordResetRequest $request): void
    {
        if (! $actor->can(PermissionKey::RecoveryHandle->value)) {
            throw new AuthorizationException(__('permissions.errors.unauthorized'));
        }

        if (! $request->status->isActive()) {
            throw new AuthorizationException(__('recovery.errors.claim_held'));
        }

        $heldByOther = $request->status === PasswordResetStatus::Claimed
            && $request->claimed_until !== null
            && $request->claimed_until->isFuture()
            && $request->claimed_by !== $actor->id;

        if ($heldByOther) {
            throw new AuthorizationException(__('recovery.errors.claim_held'));
        }

        $request->forceFill(['status' => PasswordResetStatus::Cancelled])->save();

        Audit::record(self::AUDIT_ACTION, $request, [], $actor);
    }
}
