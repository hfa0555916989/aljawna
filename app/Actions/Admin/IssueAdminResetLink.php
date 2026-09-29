<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AdminPasswordReset;
use App\Models\User;
use App\Services\Audit;
use App\Support\SaudiPhone;
use App\Support\TwoFactorPolicy;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * رابط طوارئ لتعيين كلمة مرور مدير موجود، عبر php artisan admin:reset-link فقط:
 * لا واجهة ويب له (docs/DECISIONS.md). 32 بايت عشوائي يُخزَّن مجزّأً، لمرة واحدة،
 * بصلاحية رابط الاستعادة نفسها، وإصدار جديد يُبطل السابق. لا يمسّ التحقق بخطوتين.
 *
 * متى كان TWO_FACTOR_REQUIRED=false: يشمل المشرف أيضًا، وصلاحيته 48 ساعة، وتطلب
 * صفحته تأكيد رقم الجوال (App\Support\TwoFactorPolicy، LinkPhoneConfirmation).
 */
class IssueAdminResetLink
{
    public const string AUDIT_ACTION = 'admin.reset_link_issued';

    /**
     * @return array{reset: AdminPasswordReset, plain_token: string, reset_url: string, whatsapp_url: string}
     *
     * @throws ValidationException
     */
    public function handle(string $phoneInput): array
    {
        $phone = SaudiPhone::normalize($phoneInput);
        $admin = $phone === null ? null : User::query()->where('phone', $phone)->first();
        $simple = ! TwoFactorPolicy::isRequired();

        if ($admin === null || ! self::isEligible($admin)) {
            throw ValidationException::withMessages(['phone' => $simple ? __('admin.errors.not_panel_user') : __('admin.errors.not_admin')]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages(['phone' => $simple ? __('admin.errors.inactive_panel_user') : __('admin.errors.inactive_admin')]);
        }

        $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $reset = DB::transaction(function () use ($admin, $plainToken): AdminPasswordReset {
            AdminPasswordReset::query()
                ->where('user_id', $admin->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            $reset = AdminPasswordReset::query()->create([
                'user_id' => $admin->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes(self::lifetimeMinutes()),
            ]);

            Audit::record(self::AUDIT_ACTION, $admin, ['issued_via' => 'cli', 'reset_id' => $reset->id], null);

            return $reset;
        });

        $resetUrl = route('admin.password.reset', ['token' => $plainToken]);

        return [
            'reset' => $reset,
            'plain_token' => $plainToken,
            'reset_url' => $resetUrl,
            'whatsapp_url' => 'https://wa.me/'.ltrim($admin->phone, '+').'?text='.rawurlencode(__('admin.reset.message', ['url' => $resetUrl])),
        ];
    }

    /**
     * المدير دائمًا، والمشرف أيضًا متى كان التحقق بخطوتين غير إلزامي.
     */
    public static function isEligible(User $user): bool
    {
        return TwoFactorPolicy::isRequired() ? $user->role === UserRole::Admin : $user->hasPanelRole();
    }

    public static function lifetimeMinutes(): int
    {
        return TwoFactorPolicy::isRequired()
            ? (int) config('security.recovery.link_minutes')
            : TwoFactorPolicy::simpleLinkHours() * 60;
    }
}
