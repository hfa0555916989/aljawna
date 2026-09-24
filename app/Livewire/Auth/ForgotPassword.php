<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Recovery\RequestPasswordReset;
use App\Models\User;
use App\Rules\SaudiPhoneNumber;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * صفحة نسيت كلمة المرور (docs/SPEC.md §4, FR-7, FR-8).
 */
#[Layout('layouts.app')]
#[Title('نسيت كلمة المرور')]
class ForgotPassword extends Component
{
    public string $phone = '';

    /** @var Collection<int, User>|null */
    public ?Collection $supervisors = null;

    public function submit(RequestPasswordReset $action): void
    {
        $this->validate();

        try {
            $result = $action->handle($this->phone, (string) request()->ip());
        } catch (ValidationException $exception) {
            $this->supervisors = null;

            throw $exception;
        }

        $this->supervisors = $result['supervisors'];
    }

    public function render(): View
    {
        return view('livewire.auth.forgot-password');
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:32', new SaudiPhoneNumber],
        ];
    }
}
