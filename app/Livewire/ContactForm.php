<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Contact\SendContactMessage;
use App\Rules\SaudiPhoneNumber;
use App\Services\Turnstile;
use App\Support\SaudiPhone;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * نموذج "اتصل بنا" الاختياري داخل كتلة المنشئ (docs/SPEC.md FR-55، T18).
 * يُضمَّن عبر `<livewire:contact-form />` في resources/views/pages/blocks/contact.blade.php.
 */
class ContactForm extends Component
{
    public string $name = '';

    public string $phone = '';

    public string $body = '';

    /**
     * حقل فخ للبوتات: مخفي عن المستخدم ويجب أن يبقى فارغًا.
     */
    public string $website = '';

    public string $turnstileToken = '';

    public bool $sent = false;

    public function send(SendContactMessage $sendContactMessage, Turnstile $turnstile): void
    {
        $ip = (string) request()->ip();

        $sendContactMessage->consumeAttempt($ip);

        if ($this->website !== '') {
            throw ValidationException::withMessages(['form' => __('contact.honeypot')]);
        }

        $this->validate();

        if (! $turnstile->verify($this->turnstileToken, $ip)) {
            $this->resetTurnstile();

            throw ValidationException::withMessages(['turnstile' => __('contact.captcha_failed')]);
        }

        $sendContactMessage->handle(
            $this->name,
            (string) SaudiPhone::normalize($this->phone),
            $this->body,
            $ip,
        );

        $this->reset(['name', 'phone', 'body', 'website', 'turnstileToken']);
        $this->sent = true;
    }

    public function render(): View
    {
        return view('livewire.contact-form', [
            'turnstileSiteKey' => app(Turnstile::class)->isEnabled() ? app(Turnstile::class)->siteKey() : null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['required', 'string', 'max:20', new SaudiPhoneNumber],
            'body' => ['required', 'string', 'max:1000'],
        ];
    }

    private function resetTurnstile(): void
    {
        $this->turnstileToken = '';
        $this->dispatch('turnstile-reset');
    }
}
