<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Admin\AcceptAdminInvite;
use App\Livewire\Concerns\EnrollsTwoFactor;
use App\Models\AdminInvite;
use App\Rules\FullName;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * إكمال إنشاء حساب المدير من رابط دعوة أُنشئ عبر php artisan admin:invite
 * فقط (docs/SPEC.md §2). لا رابط لهذه الصفحة في أي قائمة أو لوحة؛ يصل إليها
 * المدعو من رابط الأمر مباشرة. الرقم ثابت من الدعوة.
 */
#[Layout('layouts.app')]
#[Title('إنشاء حساب مدير')]
class JoinAdmin extends Component
{
    use EnrollsTwoFactor;

    #[Locked]
    public string $token = '';

    public string $phone = '';

    public string $full_name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $invalid = false;

    public function mount(string $token): void
    {
        $this->token = $token;

        $invite = AdminInvite::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invite === null || ! $invite->isUsable()) {
            $this->invalid = true;

            return;
        }

        $this->phone = $invite->phone;
    }

    public function join(AcceptAdminInvite $accept): void
    {
        if ($this->invalid) {
            throw ValidationException::withMessages([
                'form' => __('admin.errors.token_used'),
            ]);
        }

        $this->validate();

        $secret = $this->confirmedTwoFactorSecret();

        try {
            $result = $accept->handle($this->token, $this->full_name, $this->password, (string) request()->ip(), $secret);
        } catch (ValidationException $exception) {
            $this->invalid = true;

            throw $exception;
        }

        $this->reset('password', 'password_confirmation');
        $this->completeEnrollment($result['user'], $result['recovery_codes']);
    }

    public function render(): View
    {
        if ($this->invalid || $this->recoveryCodes !== []) {
            return view('livewire.join-admin', ['enrollment' => null]);
        }

        return view('livewire.join-admin', ['enrollment' => $this->enrollmentViewData($this->phone)]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'full_name' => ['required', 'string', 'max:255', new FullName],
            'password' => ['required', 'string', 'min:8', 'max:255', 'confirmed', $this->passwordNotPhoneRule()],
            'password_confirmation' => ['required', 'string'],
            'two_factor_code' => ['required', 'string', 'max:16'],
        ];
    }

    protected function enrollmentContext(): string
    {
        return 'join:admin:'.$this->token;
    }

    private function passwordNotPhoneRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (is_string($value) && trim($value) === $this->phone) {
                $fail(__('auth.password_equals_phone'));
            }
        };
    }
}
