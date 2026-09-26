<?php

declare(strict_types=1);

namespace App\Actions\Recovery;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\PasswordResetStatus;
use App\PermissionKey;
use App\Services\Audit;
use App\Support\SaudiPhone;
use App\UserRole;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * إنشاء طلب استعادة لرقم مسجّل (docs/SPEC.md §4, FR-7, FR-8).
 * كل محاولة تُحتسب في حد عنوان الشبكة قبل أي فحص، حتى لا تُستخدم الصفحة لاكتشاف الأرقام المسجّلة.
 */
class RequestPasswordReset
{
    public const AUDIT_ACTION = 'recovery.requested';

    /**
     * @return array{request: PasswordResetRequest, supervisors: Collection<int, User>}
     *
     * @throws ValidationException
     */
    public function handle(string $phoneInput, string $ip): array
    {
        if (RateLimiter::hit('recovery:ip:'.$ip, 3600) > (int) config('security.recovery.max_per_ip_per_hour')) {
            throw ValidationException::withMessages([
                'phone' => __('recovery.errors.ip_limit'),
            ]);
        }

        $phone = SaudiPhone::normalize($phoneInput);
        $user = $phone === null ? null : User::query()->where('phone', $phone)->first();

        if ($user === null) {
            throw ValidationException::withMessages([
                'phone' => __('recovery.errors.unregistered'),
            ]);
        }

        $active = PasswordResetRequest::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [
                PasswordResetStatus::Pending->value,
                PasswordResetStatus::Claimed->value,
                PasswordResetStatus::LinkSent->value,
            ])
            ->exists();

        if ($active) {
            throw ValidationException::withMessages([
                'phone' => __('recovery.errors.active'),
            ]);
        }

        $latest = PasswordResetRequest::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        $interval = (int) config('security.recovery.interval_minutes');

        if ($latest !== null && $latest->created_at !== null && $latest->created_at->gt(now()->subMinutes($interval))) {
            throw ValidationException::withMessages([
                'phone' => __('recovery.errors.interval', ['minutes' => $interval]),
            ]);
        }

        $request = PasswordResetRequest::query()->create([
            'user_id' => $user->id,
            'status' => PasswordResetStatus::Pending,
            'requested_ip' => $ip,
            'expires_at' => now()->addHours((int) config('security.recovery.request_hours')),
        ]);

        Audit::record(self::AUDIT_ACTION, $request, [], null);

        return [
            'request' => $request,
            'supervisors' => $this->visibleSupervisors(),
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function visibleSupervisors(): Collection
    {
        return User::query()
            ->where('role', UserRole::Supervisor)
            ->where('is_active', true)
            ->where('show_contact', true)
            ->whereHas('permissions', fn ($query) => $query->where('name', PermissionKey::RecoveryHandle->value))
            ->orderBy('id')
            ->get();
    }
}
