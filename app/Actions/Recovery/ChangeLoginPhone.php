<?php

declare(strict_types=1);

namespace App\Actions\Recovery;

use App\Models\PasswordResetRequest;
use App\Models\RecoveryLog;
use App\Models\User;
use App\PermissionKey;
use App\RecoveryLogAction;
use App\Services\Audit;
use App\Support\SaudiPhone;
use App\Support\SessionEpoch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تعديل رقم دخول صاحب الطلب بسبب يُكتب أولًا (docs/SPEC.md §4.2, FR-40).
 */
class ChangeLoginPhone
{
    public const string AUDIT_ACTION = 'recovery.phone_changed';

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, PasswordResetRequest $request, string $reason, string $phone, string $confirmation): void
    {
        if (
            ! $actor->can(PermissionKey::RecoveryHandle->value)
            || ! $actor->can(PermissionKey::RecoveryChangePhone->value)
            || ! $request->isClaimedBy($actor)
        ) {
            throw new AuthorizationException(__('recovery.errors.change_forbidden'));
        }

        if (! $actor->can('handle', $request)) {
            throw new AuthorizationException(__('recovery.errors.admin_only'));
        }

        $cleanReason = trim($reason);

        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'change_reason' => __('recovery.errors.reason_required'),
            ]);
        }

        $newPhone = SaudiPhone::normalize($phone);
        $confirmed = SaudiPhone::normalize($confirmation);

        if ($newPhone === null || $confirmed === null) {
            throw ValidationException::withMessages([
                'new_phone' => __('recovery.errors.phone'),
            ]);
        }

        if ($newPhone !== $confirmed) {
            throw ValidationException::withMessages([
                'new_phone_confirmation' => __('recovery.errors.phone_mismatch'),
            ]);
        }

        DB::transaction(function () use ($actor, $request, $cleanReason, $newPhone): void {
            $user = $request->user()->lockForUpdate()->first();

            if (! $user instanceof User) {
                throw new AuthorizationException(__('recovery.errors.change_forbidden'));
            }

            $taken = User::query()
                ->where('phone', $newPhone)
                ->whereKeyNot($user->id)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'new_phone' => __('recovery.errors.phone_taken'),
                ]);
            }

            $oldPhone = $user->phone;
            $user->forceFill(['phone' => $newPhone])->save();

            RecoveryLog::query()->create([
                'request_id' => $request->id,
                'user_id' => $user->id,
                'performed_by' => $actor->id,
                'action' => RecoveryLogAction::PhoneChanged,
                'old_phone' => $oldPhone,
                'new_phone' => $newPhone,
                'reason' => $cleanReason,
            ]);

            Audit::record(self::AUDIT_ACTION, $request, [], $actor);
        });

        $owner = $request->user()->first();

        if ($owner instanceof User) {
            SessionEpoch::bump((int) $owner->getKey());
        }
    }
}
