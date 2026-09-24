<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Supervisors\AcceptSupervisorInvite;
use App\Models\SupervisorInvite;
use App\Rules\FullName;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * إكمال تسجيل المشرف من رابط الدعوة (docs/SPEC.md §6). الرقم ثابت من الدعوة.
 */
#[Layout('layouts.app')]
#[Title('الانضمام كمشرف')]
class JoinSupervisor extends Component
{
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

        $invite = SupervisorInvite::query()
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if ($invite === null || ! $invite->isUsable()) {
            $this->invalid = true;

            return;
        }

        $this->phone = $invite->phone;
    }

    public function join(AcceptSupervisorInvite $accept): void
    {
        if ($this->invalid) {
            throw ValidationException::withMessages([
                'form' => __('supervisors.errors.token_used'),
            ]);
        }

        $this->validate();

        try {
            $user = $accept->handle($this->token, $this->full_name, $this->password, (string) request()->ip());
        } catch (ValidationException $exception) {
            $this->invalid = true;

            throw $exception;
        }

        Auth::login($user);
        Session::regenerate();

        $this->redirect($user->homeUrl());
    }

    public function render(): View
    {
        return view('livewire.join-supervisor');
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
        ];
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
