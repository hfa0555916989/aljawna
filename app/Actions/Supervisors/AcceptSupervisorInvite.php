<?php

declare(strict_types=1);

namespace App\Actions\Supervisors;

use App\Models\SupervisorInvite;
use App\Models\User;
use App\Services\Audit;
use App\Support\TwoFactorEnrollment;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * إكمال تسجيل المشرف من رابط الدعوة واستهلاك الرمز (docs/SPEC.md §6).
 */
class AcceptSupervisorInvite
{
    public const AUDIT_ACTION = 'supervisor.joined';

    /**
     * يُنشأ الحساب والتحقق بخطوتين مفعَّل فيه معًا، فلا يوجد حساب إداري بلا تحقق
     * ولو لحظة (docs/DECISIONS.md). $twoFactorSecret سرٌّ أكّده المدعو برمز صحيح.
     *
     * @return array{user: User, recovery_codes: list<string>}
     *
     * @throws ValidationException
     */
    public function handle(string $plainToken, string $fullName, string $password, string $ip, #[SensitiveParameter] string $twoFactorSecret): array
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

        return DB::transaction(function () use ($invite, $fullName, $password, $ip, $twoFactorSecret): array {
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

            $recoveryCodes = TwoFactorEnrollment::enable($user, $twoFactorSecret);

            $invite->forceFill(['accepted_at' => now()])->save();

            Audit::record(self::AUDIT_ACTION, $user, [
                'permissions' => $invite->permissions,
                'invite_id' => $invite->id,
                'two_factor_enabled' => true,
            ]);

            return ['user' => $user, 'recovery_codes' => $recoveryCodes];
        });
    }
}
