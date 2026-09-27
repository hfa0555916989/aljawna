<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\TwoFactorSetupLink;
use App\Models\User;
use App\Services\Audit;
use App\Support\SaudiPhone;
use App\Support\SessionEpoch;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * إعادة ضبط التحقق بخطوتين لمستخدم، عبر php artisan admin:reset-2fa فقط (docs/DECISIONS.md):
 * يحذف السر ورموز الاسترداد، وينهي كل جلساته.
 *
 * لأدوار اللوحة يصدر رابط إعداد لمرة واحدة (32 بايت يُخزَّن مجزّأً، مدته من
 * security.two_factor.setup_link_minutes، ويُبطل ما قبله): كلمة المرور وحدها لا
 * تُدخل الحساب حتى يُعدّ صاحبه التحقق من هذا الرابط (T20).
 */
class ResetTwoFactor
{
    public const string AUDIT_ACTION = 'auth.two_factor_reset';

    /**
     * @return array{user: User, setup_url: string|null, whatsapp_url: string|null}
     *
     * @throws ValidationException
     */
    public function handle(string $phoneInput): array
    {
        $phone = SaudiPhone::normalize($phoneInput);
        $user = $phone === null ? null : User::query()->where('phone', $phone)->first();

        if ($user === null) {
            throw ValidationException::withMessages(['phone' => __('admin.errors.user_not_found')]);
        }

        $wasEnabled = $user->hasTwoFactorEnabled();
        $plainToken = $user->hasPanelRole() ? rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=') : null;

        DB::transaction(function () use ($user, $wasEnabled, $plainToken): void {
            $user->forceFill([
                'app_authentication_secret' => null,
                'app_authentication_recovery_codes' => null,
            ])->save();

            SessionEpoch::bump((int) $user->getKey());

            TwoFactorSetupLink::query()
                ->where('user_id', $user->id)
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            $link = $plainToken === null ? null : TwoFactorSetupLink::query()->create([
                'user_id' => $user->id,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addMinutes((int) config('security.two_factor.setup_link_minutes')),
            ]);

            Audit::record(self::AUDIT_ACTION, $user, array_filter([
                'issued_via' => 'cli',
                'was_enabled' => $wasEnabled,
                'setup_link_id' => $link?->id,
            ], fn (mixed $value): bool => $value !== null), null);
        });

        if ($plainToken === null) {
            return ['user' => $user, 'setup_url' => null, 'whatsapp_url' => null];
        }

        $setupUrl = route('two-factor.setup', ['token' => $plainToken]);

        return [
            'user' => $user,
            'setup_url' => $setupUrl,
            'whatsapp_url' => 'https://wa.me/'.ltrim($user->phone, '+').'?text='.rawurlencode(__('admin.two_factor_reset.message', ['url' => $setupUrl])),
        ];
    }
}
