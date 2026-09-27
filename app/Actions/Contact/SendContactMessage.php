<?php

declare(strict_types=1);

namespace App\Actions\Contact;

use App\Models\ContactMessage;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * إرسال رسالة "اتصل بنا" (docs/SPEC.md §9 contact_messages، FR-55). محمية بحدّ
 * معدّل لكل IP، والفخ والتحقق من Turnstile يتوليّان في App\Livewire\ContactForm
 * (مطابقةً لـ App\Actions\Auth\RegisterUser).
 */
class SendContactMessage
{
    private const DECAY_SECONDS = 3600;

    /**
     * يستهلك محاولة إرسال من حدّ الـ IP. كل محاولة تُحتسب، ناجحة كانت أو فاشلة.
     *
     * @throws ValidationException
     */
    public function consumeAttempt(string $ip): void
    {
        $key = $this->throttleKey($ip);

        if (RateLimiter::tooManyAttempts($key, (int) config('security.contact.max_per_ip_per_hour'))) {
            throw ValidationException::withMessages([
                'form' => __('contact.throttled', [
                    'minutes' => (int) ceil(RateLimiter::availableIn($key) / 60),
                ]),
            ]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);
    }

    /**
     * @param  string  $phone  رقم مطبَّع بصيغة +9665XXXXXXXX
     */
    public function handle(string $name, string $phone, string $body, string $ip): ContactMessage
    {
        return ContactMessage::query()->create([
            'name' => trim($name),
            'phone' => $phone,
            'body' => trim($body),
            'ip' => $ip,
        ]);
    }

    private function throttleKey(string $ip): string
    {
        return 'contact:ip:'.$ip;
    }
}
