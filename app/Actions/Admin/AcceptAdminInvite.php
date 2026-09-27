<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AdminInvite;
use App\Models\User;
use App\Services\Audit;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إكمال إنشاء حساب المدير من رابط الدعوة واستهلاك الرمز (docs/SPEC.md §2).
 * توازي App\Actions\Supervisors\AcceptSupervisorInvite لكن بدور مدير وبلا صلاحيات فردية.
 */
class AcceptAdminInvite
{
    public const AUDIT_ACTION = 'admin.joined';

    /**
     * @throws ValidationException
     */
    public function handle(string $plainToken, string $fullName, string $password, string $ip): User
    {
        $invite = AdminInvite::query()
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($invite === null || $invite->accepted_at !== null) {
            throw ValidationException::withMessages([
                'form' => __('admin.errors.token_used'),
            ]);
        }

        if ($invite->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'form' => __('admin.errors.token_expired'),
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
                'role' => UserRole::Admin,
                'is_active' => true,
                'show_contact' => false,
                'registered_ip' => $ip,
            ]);

            $invite->forceFill(['accepted_at' => now()])->save();

            Audit::record(self::AUDIT_ACTION, $user, ['invite_id' => $invite->id]);

            return $user;
        });
    }
}
