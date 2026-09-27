<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Services\Audit;
use App\Support\SaudiPhone;
use App\Support\SessionEpoch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إعادة ضبط التحقق بخطوتين لمستخدم، عبر php artisan admin:reset-2fa فقط (docs/DECISIONS.md):
 * يحذف السر ورموز الاسترداد، وينهي كل جلساته، فيُلزَم بإعداد التحقق من جديد عند
 * دخوله التالي إن كان من أدوار اللوحة.
 */
class ResetTwoFactor
{
    public const string AUDIT_ACTION = 'auth.two_factor_reset';

    /**
     * @throws ValidationException
     */
    public function handle(string $phoneInput): User
    {
        $phone = SaudiPhone::normalize($phoneInput);
        $user = $phone === null ? null : User::query()->where('phone', $phone)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['phone' => __('admin.errors.user_not_found')]);
        }

        $wasEnabled = $user->hasTwoFactorEnabled();

        DB::transaction(function () use ($user, $wasEnabled): void {
            $user->forceFill([
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();

            SessionEpoch::bump((int) $user->getKey());

            Audit::record(self::AUDIT_ACTION, $user, ['issued_via' => 'cli', 'was_enabled' => $wasEnabled], null);
        });

        return $user;
    }
}
