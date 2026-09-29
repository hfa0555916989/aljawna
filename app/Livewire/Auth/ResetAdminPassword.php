<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Admin\CompleteAdminPasswordReset;
use App\Models\AdminPasswordReset;
use App\Support\TwoFactorPolicy;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * تعيين كلمة مرور مدير من رابط الطوارئ php artisan admin:reset-link. لا رابط لهذه
 * الصفحة في أي قائمة؛ ويبقى التحقق بخطوتين مطلوبًا عند الدخول بعدها. متى كان
 * TWO_FACTOR_REQUIRED=false تطلب رقم الجوال لتأكيده مع كلمة المرور الجديدة.
 */
#[Layout('layouts.app')]
#[Title('تعيين كلمة مرور جديدة')]
class ResetAdminPassword extends Component
{
    #[Locked]
    public string $token = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $invalid = false;

    public function mount(string $token): void
    {
        $this->token = $token;

        $reset = AdminPasswordReset::query()->where('token_hash', hash('sha256', $token))->first();

        $this->invalid = $reset === null || ! $reset->isUsable();
    }

    public function resetPassword(CompleteAdminPasswordReset $complete): void
    {
        $this->validate();

        try {
            $complete->handle($this->token, $this->password, TwoFactorPolicy::isRequired() ? null : $this->phone);
        } catch (ValidationException $exception) {
            if (TwoFactorPolicy::isRequired()) {
                $this->invalid = true;

                throw $exception;
            }

            $this->reset('password', 'password_confirmation');
            $this->invalid = ! (AdminPasswordReset::query()->where('token_hash', hash('sha256', $this->token))->first()?->isUsable() ?? false);

            throw $exception;
        }

        $this->redirectRoute('login');
    }

    public function render(): View
    {
        return view('livewire.auth.reset-password', ['askPhone' => ! TwoFactorPolicy::isRequired()]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        if (! TwoFactorPolicy::isRequired()) {
            return [
                'phone' => ['required', 'string', 'max:32'],
                'password' => ['required', 'string', 'max:255', 'confirmed', TwoFactorPolicy::simplePasswordRule(), $this->passwordNotPhoneRule()],
                'password_confirmation' => ['required', 'string'],
            ];
        }

        return [
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', $this->passwordNotPhoneRule()],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    private function passwordNotPhoneRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $phone = AdminPasswordReset::query()
                ->where('token_hash', hash('sha256', $this->token))
                ->first()?->user?->phone;

            if (is_string($value) && $phone !== null && trim($value) === $phone) {
                $fail(__('auth.password_equals_phone'));
            }
        };
    }
}
