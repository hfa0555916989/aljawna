<?php

declare(strict_types=1);

namespace App\Actions\Supervisors;

use App\Models\SupervisorInvite;
use App\Models\User;
use App\Services\Audit;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إكمال تسجيل المشرف من رابط الدعوة واستهلاك الرمز (docs/SPEC.md §6).
 */
class AcceptSupervisorInvite
{
    public const AUDIT_ACTION = 'supervisor.joined';

    /**
     * @throws ValidationException
     */
    public function handle(string $plainToken, string $fullName, string $password, string $ip): User
    {
        $invite = SupervisorInvite::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($invite === null || $invite->accepted_at !== null) {
            throw ValidationException::withMessages([
                'form' => __('supervisors.errors.token_used'),
            ]);
        }

        if ($invite->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'form' => __('supervisors.errors.token_expired'),
            ]);
        }

        if (User::query()->where('phone', $invite->phone)->exists()) {
            throw ValidationException::withMessages([
                'form' => __('auth.phone_taken'),
            ]);
        }

        return DB::transaction(function () use ($invite, $fullName, $password, $ip): User {
            $user = User::query()->create([
                'full_name' => preg_replace('/\s+/u', ' ', trim($fullName)) ?? trim($fullName),
                'phone' => $invite->phone,
                'password' => $password,
                'role' => UserRole::Supervisor,
                'is_active' => true,
                'show_contact' => false,
                'registered_ip' => $ip,
            ]);

            $user->syncPermissions($invite->permissions);

            $invite->forceFill(['accepted_at' => now()])->save();

            Audit::record(self::AUDIT_ACTION, $user, [
                'permissions' => $invite->permissions,
                'invite_id' => $invite->id,
            ]);

            return $user;
        });
    }
}
