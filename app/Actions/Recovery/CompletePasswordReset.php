<?php

declare(strict_types=1);

namespace App\Actions\Recovery;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\PasswordResetStatus;
use App\Services\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تعيين كلمة المرور من الرابط وإغلاق الطلب (FR-11, FR-12).
 * تغيير كلمة المرور يُبطل الجلسات القديمة عبر AuthenticateSession.
 */
class CompletePasswordReset
{
    public const AUDIT_ACTION = 'recovery.completed';

    /**
     * @throws ValidationException
     */
    public function handle(string $plainToken, string $password): User
    {
        $token = PasswordResetToken::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($token === null || $token->used_at !== null) {
            throw ValidationException::withMessages([
                'form' => __('recovery.errors.token_used'),
            ]);
        }

        if ($token->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'form' => __('recovery.errors.token_expired'),
            ]);
        }

        return DB::transaction(function () use ($token, $password): User {
            $request = $token->request()->lockForUpdate()->first();

            if ($request === null || $request->status !== PasswordResetStatus::LinkSent) {
                throw ValidationException::withMessages([
                    'form' => __('recovery.errors.token_used'),
                ]);
            }

            $user = $request->user()->lockForUpdate()->first();

            if (! $user instanceof User) {
                throw ValidationException::withMessages([
                    'form' => __('recovery.errors.token_used'),
                ]);
            }

            $user->forceFill(['password' => $password])->save();
            $token->forceFill(['used_at' => now()])->save();
            $request->forceFill(['status' => PasswordResetStatus::Completed])->save();

            Audit::record(self::AUDIT_ACTION, $request, ['token_id' => $token->id], null);

            return $user;
        });
    }
}
