<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * التحقق من رمز Cloudflare Turnstile على الخادم (docs/SPEC.md §11, §12.3).
 */
class Turnstile
{
    public function isEnabled(): bool
    {
        return (bool) config('services.turnstile.enabled');
    }

    public function siteKey(): ?string
    {
        $siteKey = config('services.turnstile.site_key');

        return is_string($siteKey) && $siteKey !== '' ? $siteKey : null;
    }

    /**
     * يعيد true إن كان الرمز صالحًا، أو إن كان Turnstile معطَّلًا في الإعدادات.
     */
    public function verify(?string $token, ?string $ip): bool
    {
        if (! $this->isEnabled()) {
            return true;
        }

        $secret = config('services.turnstile.secret_key');

        if ($token === null || $token === '' || ! is_string($secret) || $secret === '') {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout(5)
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException) {
            return false;
        }

        return $response->successful() && $response->json('success') === true;
    }
}
