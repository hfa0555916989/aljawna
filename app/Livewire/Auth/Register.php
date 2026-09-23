<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterUser;
use App\Models\User;
use App\Rules\FullName;
use App\Rules\SaudiPhoneNumber;
use App\Services\Turnstile;
use App\Support\SaudiPhone;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * صفحة تسجيل المبادر /register (docs/SPEC.md §3, FR-1..6).
 */
#[Title('إنشاء حساب')]
class Register extends Component
{
    public string $full_name = '';

    public string $phone = '';

    public string $phone_confirmation = '';

    public string $password = '';

    public string $password_confirmation = '';

    /**
     * حقل فخ للبوتات: مخفي عن المستخدم ويجب أن يبقى فارغًا.
     */
    public string $website = '';

    public string $turnstileToken = '';

    /**
     * مؤشر تطابق الجوال وتأكيده: null قبل كتابة التأكيد.
     */
    #[Computed]
    public function phoneConfirmationMatches(): ?bool
    {
        if (trim($this->phone_confirmation) === '') {
            return null;
        }

        $phone = SaudiPhone::normalize($this->phone);
        $confirmation = SaudiPhone::normalize($this->phone_confirmation);

        if ($phone !== null && $confirmation !== null) {
            return $phone === $confirmation;
        }

        return trim($this->phone) === trim($this->phone_confirmation);
    }

    public function register(RegisterUser $registerUser, Turnstile $turnstile): void
    {
        $ip = (string) request()->ip();

        $registerUser->consumeAttempt($ip);

        if ($this->website !== '') {
            throw ValidationException::withMessages(['form' => __('auth.honeypot')]);
        }

        $this->validate();

        if (! $turnstile->verify($this->turnstileToken, $ip)) {
            $this->resetTurnstile();

            throw ValidationException::withMessages(['turnstile' => __('auth.captcha_failed')]);
        }

        try {
            $user = $registerUser->handle(
                $this->full_name,
                (string) SaudiPhone::normalize($this->phone),
                $this->password,
                $ip,
            );
        } catch (ValidationException $exception) {
            $this->resetTurnstile();

            throw $exception;
        }

        Auth::login($user);
        Session::regenerate();

        $this->redirectRoute('dashboard');
    }

    public function render(): View
    {
        return view('livewire.auth.register', [
            'turnstileSiteKey' => app(Turnstile::class)->isEnabled() ? app(Turnstile::class)->siteKey() : null,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255', new FullName],
            'phone' => ['required', 'string', 'max:32', new SaudiPhoneNumber, $this->uniquePhoneRule()],
            'phone_confirmation' => ['required', 'string', 'max:32', $this->phoneConfirmationRule()],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', $this->passwordNotPhoneRule()],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    private function uniquePhoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $phone = is_string($value) ? SaudiPhone::normalize($value) : null;

            if ($phone !== null && User::query()->where('phone', $phone)->exists()) {
                $fail(__('auth.phone_taken'));
            }
        };
    }

    private function phoneConfirmationRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if ($this->phoneConfirmationMatches() !== true) {
                $fail(__('auth.phone_mismatch'));
            }
        };
    }

    private function passwordNotPhoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $phone = SaudiPhone::normalize($this->phone);

            if (trim($value) === trim($this->phone) || ($phone !== null && SaudiPhone::normalize($value) === $phone)) {
                $fail(__('auth.password_equals_phone'));
            }
        };
    }

    private function resetTurnstile(): void
    {
        $this->turnstileToken = '';
        $this->dispatch('turnstile-reset');
    }
}
