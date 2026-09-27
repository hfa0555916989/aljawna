<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AdminPasswordReset;
use App\Models\User;
use App\Services\Audit;
use App\Support\SaudiPhone;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * رابط طوارئ لتعيين كلمة مرور مدير موجود، عبر php artisan admin:reset-link فقط:
 * لا واجهة ويب له (docs/DECISIONS.md). 32 بايت عشوائي يُخزَّن مجزّأً، لمرة واحدة،
 * بصلاحية رابط الاستعادة نفسها، وإصدار جديد يُبطل السابق. لا يمسّ التحقق بخطوتين.
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

        if ($admin === null || $admin->role !== UserRole::Admin) {
            throw ValidationException::withMessages(['phone' => __('admin.errors.not_admin')]);
        }

        if (! $admin->is_active) {
            throw ValidationException::withMessages(['phone' => __('admin.errors.inactive_admin')]);
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
                'expires_at' => now()->addMinutes((int) config('security.recovery.link_minutes')),
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
}
