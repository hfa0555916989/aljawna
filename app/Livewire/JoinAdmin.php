<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Admin\AcceptAdminInvite;
use App\Livewire\Concerns\EnrollsTwoFactor;
use App\Models\AdminInvite;
use App\Rules\FullName;
use App\Support\TwoFactorPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * إكمال إنشاء حساب المدير من رابط دعوة أُنشئ عبر php artisan admin:invite
 * فقط (docs/SPEC.md §2). لا رابط لهذه الصفحة في أي قائمة أو لوحة؛ يصل إليها
 * المدعو من رابط الأمر مباشرة. الرقم ثابت من الدعوة.
 *
 * متى كان TWO_FACTOR_REQUIRED=false: لا يُعرض رقم الدعوة، بل يكتبه المدعو ليؤكده،
 * ثم الاسم وكلمة المرور فقط بلا QR ولا رموز استرداد (App\Support\TwoFactorPolicy).
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

        if (TwoFactorPolicy::isRequired()) {
            $this->phone = $invite->phone;
        }
    }

    public function join(AcceptAdminInvite $accept): void
    {
        if ($this->invalid) {
            throw ValidationException::withMessages([
                'form' => __('admin.errors.token_used'),
            ]);
        }

        if (! TwoFactorPolicy::isRequired()) {
            $this->joinWithoutTwoFactor($accept);

            return;
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
        $simple = ! TwoFactorPolicy::isRequired();

        if ($simple || $this->invalid || $this->recoveryCodes !== []) {
            return view('livewire.join-admin', ['enrollment' => null, 'simple' => $simple]);
        }

        return view('livewire.join-admin', ['enrollment' => $this->enrollmentViewData($this->phone), 'simple' => false]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        if (! TwoFactorPolicy::isRequired()) {
            return [
                'phone' => ['required', 'string', 'max:32'],
                'full_name' => ['required', 'string', 'max:255', new FullName],
                'password' => ['required', 'string', 'max:255', 'confirmed', TwoFactorPolicy::simplePasswordRule(), $this->passwordNotPhoneRule()],
                'password_confirmation' => ['required', 'string'],
            ];
        }

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

    /**
     * الوضع المبسّط: تأكيد رقم الدعوة ثم إنشاء الحساب والدخول مباشرة. الرقم الخاطئ
     * لا يُبطل الصفحة إلا إذا أُلغي الرابط ببلوغ حد المحاولات.
     */
    private function joinWithoutTwoFactor(AcceptAdminInvite $accept): void
    {
        $this->validate();

        try {
            $result = $accept->handle($this->token, $this->full_name, $this->password, (string) request()->ip(), null, $this->phone);
        } catch (ValidationException $exception) {
            $this->reset('password', 'password_confirmation');
            $this->invalid = ! (AdminInvite::query()->where('token_hash', hash('sha256', $this->token))->first()?->isUsable() ?? false);

            throw $exception;
        }

        $this->reset('password', 'password_confirmation');

        Auth::login($result['user']);
        Session::regenerate();

        $this->redirect($result['user']->homeUrl());
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
