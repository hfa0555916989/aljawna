<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\PermissionKey;
use App\Services\Audit;
use App\Support\SessionEpoch;
use App\UserRole;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تعطيل حساب مبادر أو إعادة تفعيله بسبب يُحفظ في سجل التدقيق (docs/SPEC.md §12.7, FR-22).
 * التعطيل يسري فورًا: تنتهي جلسات الحساب في الطلب التالي، والدخول يرفضه برسالة التواصل مع الإدارة.
 */
class SetInitiatorActive
{
    public const string AUDIT_SUSPENDED = 'user.suspended';

    public const string AUDIT_ACTIVATED = 'user.activated';

    public const int REASON_MAX_LENGTH = 255;

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, User $user, bool $active, string $reason): void
    {
        if (! $actor->can(PermissionKey::UsersSuspend->value)) {
            throw new AuthorizationException(__('permissions.errors.unauthorized'));
        }

        if ($user->role !== UserRole::User) {
            throw new AuthorizationException(__('security.errors.not_initiator'));
        }

        $cleanReason = trim($reason);

        if ($cleanReason === '') {
            throw ValidationException::withMessages([
                'toggle_reason' => __('security.errors.reason_required'),
            ]);
        }

        if (mb_strlen($cleanReason) > self::REASON_MAX_LENGTH) {
            throw ValidationException::withMessages([
                'toggle_reason' => __('security.errors.reason_too_long', ['max' => self::REASON_MAX_LENGTH]),
            ]);
        }

        DB::transaction(function () use ($actor, $user, $active, $cleanReason): void {
            $locked = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->is_active === $active) {
                return;
            }

            $locked->forceFill(['is_active' => $active])->save();

            if (! $active) {
                SessionEpoch::bump((int) $locked->getKey());
            }

            Audit::record(
                $active ? self::AUDIT_ACTIVATED : self::AUDIT_SUSPENDED,
                $locked,
                ['reason' => $cleanReason],
                $actor,
            );
        });

        $user->refresh();
    }
}
