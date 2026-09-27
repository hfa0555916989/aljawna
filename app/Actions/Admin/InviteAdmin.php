<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Models\AdminInvite;
use App\Models\User;
use App\Services\Audit;
use App\Support\SaudiPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * دعوة حساب مدير برقم جواله، عبر php artisan admin:invite فقط: لا واجهة ويب
 * لهذا الإجراء (docs/SPEC.md §2). الرمز 32 بايت عشوائي يُخزَّن مجزّأً فقط،
 * وينتهي بعد 48 ساعة، ودعوة جديدة لنفس الرقم تُبطل السابقة لها.
 */
class InviteAdmin
{
    public const AUDIT_ACTION = 'admin.invited';

    public const LIFETIME_HOURS = 48;

    /**
     * @return array{invite: AdminInvite, plain_token: string, join_url: string, whatsapp_url: string}
     *
     * @throws ValidationException
     */
    public function handle(string $phoneInput): array
    {
        $phone = SaudiPhone::normalize($phoneInput);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => __('admin.errors.phone'),
            ]);
        }

        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => __('auth.phone_taken'),
            ]);
        }

        $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $invite = DB::transaction(function () use ($phone, $plainToken): AdminInvite {
            AdminInvite::query()
                ->where('phone', $phone)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            $invite = AdminInvite::query()->create([
                'phone' => $phone,
                'token_hash' => hash('sha256', $plainToken),
                'expires_at' => now()->addHours(self::LIFETIME_HOURS),
            ]);

            Audit::record(self::AUDIT_ACTION, $invite, ['issued_via' => 'cli'], null);

            return $invite;
        });

        $joinUrl = route('admin.join', ['token' => $plainToken]);

        return [
            'invite' => $invite,
            'plain_token' => $plainToken,
            'join_url' => $joinUrl,
            'whatsapp_url' => $this->whatsappUrl($phone, $joinUrl),
        ];
    }

    private function whatsappUrl(string $phone, string $joinUrl): string
    {
        $text = rawurlencode(__('admin.invite.message', ['url' => $joinUrl]));

        return 'https://wa.me/'.ltrim($phone, '+').'?text='.$text;
    }
}
