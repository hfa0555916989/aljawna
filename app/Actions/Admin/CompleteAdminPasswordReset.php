<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AdminPasswordReset;
use App\Models\User;
use App\Services\Audit;
use App\Support\LinkPhoneConfirmation;
use App\Support\SessionEpoch;
use App\Support\TwoFactorPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تعيين كلمة مرور المدير من رابط admin:reset-link واستهلاكه، وإنهاء كل جلساته القائمة.
 * متى كان TWO_FACTOR_REQUIRED=false: يشمل المشرف، ويجب $phoneInput مطابقًا لرقم
 * الحساب، وإلا رُفض برسالة عامة واحتُسبت محاولة حتى يُلغى الرابط (LinkPhoneConfirmation).
 */
class CompleteAdminPasswordReset
{
    public const string AUDIT_ACTION = 'admin.password_reset_completed';

    /**
     * @throws ValidationException
     */
    public function handle(string $plainToken, string $password, ?string $phoneInput = null): User
    {
        if (! TwoFactorPolicy::isRequired()) {
            $this->confirmPhone($plainToken, $phoneInput);
        }

        return DB::transaction(function () use ($plainToken, $password): User {
            $reset = AdminPasswordReset::query()
                ->where('token_hash', hash('sha256', $plainToken))
                ->lockForUpdate()
                ->first();

            if ($reset === null || ! $reset->isUsable()) {
                throw ValidationException::withMessages(['form' => __('recovery.errors.token_used')]);
            }

            $admin = User::query()->whereKey($reset->user_id)->lockForUpdate()->first();

            if ($admin === null || ! IssueAdminResetLink::isEligible($admin) || ! $admin->is_active) {
                throw ValidationException::withMessages(['form' => __('recovery.errors.token_used')]);
            }

            $admin->forceFill(['password' => $password])->save();
            $reset->forceFill(['used_at' => now()])->save();
            SessionEpoch::bump((int) $admin->getKey());

            Audit::record(self::AUDIT_ACTION, $admin, ['reset_id' => $reset->id], null);

            return $admin;
        });
    }

    /**
     * يُنفَّذ قبل معاملة التعيين كي يبقى عدّ المحاولات الخاطئة محفوظًا عند الرفض.
     *
     * @throws ValidationException
     */
    private function confirmPhone(string $plainToken, ?string $phoneInput): void
    {
        $reset = AdminPasswordReset::query()->where('token_hash', hash('sha256', $plainToken))->first();
        $phone = $reset?->user?->phone;

        if ($reset === null || ! $reset->isUsable() || $phone === null) {
            throw ValidationException::withMessages(['form' => __('recovery.errors.token_used')]);
        }

        if (! LinkPhoneConfirmation::confirm($reset, $phone, (string) $phoneInput)) {
            throw ValidationException::withMessages(['phone' => __('admin.errors.phone_mismatch')]);
        }
    }
}
