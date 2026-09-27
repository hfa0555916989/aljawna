<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AdminPasswordReset;
use App\Models\User;
use App\Services\Audit;
use App\Support\SessionEpoch;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تعيين كلمة مرور المدير من رابط admin:reset-link واستهلاكه، وإنهاء كل جلساته القائمة.
 */
class CompleteAdminPasswordReset
{
    public const string AUDIT_ACTION = 'admin.password_reset_completed';

    /**
     * @throws ValidationException
     */
    public function handle(string $plainToken, string $password): User
    {
        return DB::transaction(function () use ($plainToken, $password): User {
            $reset = AdminPasswordReset::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if ($reset === null || ! $reset->isUsable()) {
                throw ValidationException::withMessages(['form' => __('recovery.errors.token_used')]);
            }

            $admin = User::query()->whereKey($reset->user_id)->lockForUpdate()->first();

            if ($admin === null || $admin->role !== UserRole::Admin || ! $admin->is_active) {
                throw ValidationException::withMessages(['form' => __('recovery.errors.token_used')]);
            }

            $admin->forceFill(['password' => $password])->save();
            $reset->forceFill(['used_at' => now()])->save();
            SessionEpoch::bump((int) $admin->getKey());

            Audit::record(self::AUDIT_ACTION, $admin, ['reset_id' => $reset->id], null);

            return $admin;
        });
    }
}
