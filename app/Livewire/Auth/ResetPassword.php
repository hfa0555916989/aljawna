<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Recovery\CompletePasswordReset;
use App\Models\PasswordResetToken;
use App\Support\SaudiPhone;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * تعيين كلمة مرور جديدة من رابط المشرف (FR-11, FR-12).
 */
#[Layout('layouts.app')]
#[Title('تعيين كلمة مرور جديدة')]
class ResetPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $invalid = false;

    public function mount(string $token): void
    {
        $this->token = $token;
    }

    public function resetPassword(CompletePasswordReset $complete): void
    {
        $this->validate();

        try {
            $complete->handle($this->token, $this->password);
        } catch (ValidationException $exception) {
            $this->invalid = true;

            throw $exception;
        }

        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', $this->passwordNotPhoneRule()],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    private function passwordNotPhoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $token = PasswordResetToken::query()
                ->where('token_hash', hash('sha256', $this->token))
                ->first();
            $phone = $token?->request?->user?->phone;

            if ($phone !== null && (trim($value) === $phone || SaudiPhone::normalize($value) === $phone)) {
                $fail(__('auth.password_equals_phone'));
            }
        };
    }
}
