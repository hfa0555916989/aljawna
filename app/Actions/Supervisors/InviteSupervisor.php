<?php

declare(strict_types=1);

namespace App\Actions\Supervisors;

use App\Models\SupervisorInvite;
use App\Models\User;
use App\Services\Audit;
use App\Support\PermissionTemplates;
use App\Support\SaudiPhone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * دعوة مشرف برقم جواله (docs/SPEC.md FR-19, §6).
 * الرمز 32 بايت عشوائي، يُخزَّن مجزّأ، وينتهي بعد 72 ساعة.
 */
class InviteSupervisor
{
    public const AUDIT_ACTION = 'supervisor.invited';

    public const LIFETIME_HOURS = 72;

    public function __construct(private UpdateSupervisorPermissions $permissions) {}

    /**
     * @param  list<string>  $permissions
     * @return array{invite: SupervisorInvite, whatsapp_url: string}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, string $phoneInput, array $permissions, ?string $template = null): array
    {
        $phone = SaudiPhone::normalize($phoneInput);

        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => __('supervisors.errors.phone'),
            ]);
        }

        if ($permissions === [] && $template !== null && $template !== '') {
            try {
                $permissions = PermissionTemplates::permissions($template);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages([
                    'template' => __('supervisors.errors.template'),
                ]);
            }
        }

        $this->permissions->assertCanGrant($actor, $permissions);

        if (User::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => __('auth.phone_taken'),
            ]);
        }

        $plainToken = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');

        $invite = DB::transaction(function () use ($actor, $phone, $permissions, $plainToken): SupervisorInvite {
            SupervisorInvite::query()
                ->where('phone', $phone)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);

            $invite = SupervisorInvite::query()->create([
                'phone' => $phone,
                'token_hash' => hash('sha256', $plainToken),
                'permissions' => array_values(array_unique($permissions)),
                'invited_by' => $actor->id,
                'expires_at' => now()->addHours(self::LIFETIME_HOURS),
            ]);

            Audit::record(self::AUDIT_ACTION, $invite, [
                'permissions' => $invite->permissions,
            ], $actor);

            return $invite;
        });

        return [
            'invite' => $invite,
            'whatsapp_url' => $this->whatsappUrl($phone, $plainToken),
        ];
    }

    private function whatsappUrl(string $phone, string $plainToken): string
    {
        $joinUrl = route('supervisors.join', ['token' => $plainToken]);
        $text = rawurlencode(__('supervisors.invite.message', ['url' => $joinUrl]));

        return 'https://wa.me/'.ltrim($phone, '+').'?text='.$text;
    }
}
