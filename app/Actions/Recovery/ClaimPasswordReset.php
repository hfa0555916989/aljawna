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
 * حجز طلب الاستعادة لمشرف واحد لمدة 15 دقيقة (docs/SPEC.md §4).
 */
class ClaimPasswordReset
{
    public const AUDIT_ACTION = 'recovery.claimed';

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, PasswordResetRequest $request): void
    {
        if (! $actor->can(PermissionKey::RecoveryHandle->value)) {
            throw new AuthorizationException(__('permissions.errors.unauthorized'));
        }

        if ($request->status === PasswordResetStatus::Claimed && $request->isClaimedBy($actor)) {
            return;
        }

        $heldByOther = $request->status === PasswordResetStatus::Claimed
            && $request->claimed_until !== null
            && $request->claimed_until->isFuture()
            && $request->claimed_by !== $actor->id;

        if ($heldByOther || ! in_array($request->status, [PasswordResetStatus::Pending, PasswordResetStatus::Claimed], true)) {
            throw new AuthorizationException(__('recovery.errors.claim_held'));
        }

        $request->forceFill([
            'status' => PasswordResetStatus::Claimed,
            'claimed_by' => $actor->id,
            'claimed_until' => now()->addMinutes((int) config('security.recovery.claim_minutes')),
        ])->save();

        Audit::record(self::AUDIT_ACTION, $request, [], $actor);
    }
}
