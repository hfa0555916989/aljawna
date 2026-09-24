<?php

declare(strict_types=1);

namespace App\Actions\Recovery;

use App\Models\PasswordResetRequest;
use App\Models\PasswordResetToken;
use App\Models\RecoveryLog;
use App\Models\User;
use App\PasswordResetStatus;
use App\PermissionKey;
use App\RecoveryLogAction;
use App\Services\Audit;
use App\Support\SaudiPhone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إصدار رابط التعيين لحظة الإرسال وإرجاع رابط واتساب (docs/SPEC.md §4, FR-10, FR-11).
 */
class SendPasswordResetLink
{
    public const AUDIT_ACTION = 'recovery.link_sent';

    /**
     * @return array{token: PasswordResetToken, whatsapp_url: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, PasswordResetRequest $request, bool $otherNumber, ?string $reason, ?string $otherPhone): array
    {
        if (! $actor->can(PermissionKey::RecoveryHandle->value) || ! $request->isClaimedBy($actor)) {
            throw new AuthorizationException(__('recovery.errors.claim_held'));
        }

        $request->loadMissing('user');
        $user = $request->user;

        if ($user === null) {
            throw new AuthorizationException(__('recovery.errors.claim_held'));
        }

        $sentTo = $user->phone;
        $cleanReason = null;

        if ($otherNumber) {
            if (! $actor->can(PermissionKey::RecoveryOtherNumber->value)) {
                throw new AuthorizationException(__('recovery.errors.other_forbidden'));
            }

            $cleanReason = trim((string) $reason);

            if ($cleanReason === '') {
                throw ValidationException::withMessages([
                    'reason' => __('recovery.errors.reason_required'),
                ]);
            }

            $sentTo = SaudiPhone::normalize($otherPhone);

            if ($sentTo === null) {
                throw ValidationException::withMessages([
                    'other_phone' => __('recovery.errors.phone'),
                ]);
            }
        }

        $plain = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $token = DB::transaction(function () use ($actor, $request, $user, $plain, $sentTo, $otherNumber, $cleanReason): PasswordResetToken {
            PasswordResetToken::query()
                ->where('request_id', $request->id)
                ->whereNull('used_at')
                ->update(['expires_at' => now()]);

            $token = PasswordResetToken::query()->create([
                'request_id' => $request->id,
                'issued_by' => $actor->id,
                'token_hash' => hash('sha256', $plain),
                'sent_to_phone' => $sentTo,
                'is_other_number' => $otherNumber,
                'reason' => $cleanReason,
                'expires_at' => now()->addMinutes((int) config('security.recovery.link_minutes')),
            ]);

            $request->forceFill(['status' => PasswordResetStatus::LinkSent])->save();

            RecoveryLog::query()->create([
                'request_id' => $request->id,
                'user_id' => $user->id,
                'performed_by' => $actor->id,
                'action' => $otherNumber ? RecoveryLogAction::LinkOther : RecoveryLogAction::LinkRegistered,
                'sent_to_phone' => $sentTo,
                'reason' => $cleanReason,
            ]);

            Audit::record(self::AUDIT_ACTION, $request, [
                'is_other_number' => $otherNumber,
                'token_id' => $token->id,
            ], $actor);

            return $token;
        });

        $url = route('password.reset', ['token' => $plain]);
        $text = rawurlencode(__('recovery.message', ['url' => $url]));

        return [
            'token' => $token,
            'whatsapp_url' => 'https://wa.me/'.ltrim($sentTo, '+').'?text='.$text,
        ];
    }
}
