<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\LoginUser;
use Filament\Pages\Dashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * صفحة دخول المبادر /login بالجوال وكلمة المرور (docs/SPEC.md §3, FR-5).
 * المشرف والمدير يُحوَّلان مباشرة إلى لوحة الإدارة /admin.
 */
#[Title('تسجيل الدخول')]
class Login extends Component
{
    public string $phone = '';

    public string $password = '';

    public function login(LoginUser $loginUser): void
    {
        $this->validate([
            'phone' => ['required', 'string', 'max:32'],
            'password' => ['required', 'string', 'max:255'],
        ]);

        try {
            $user = $loginUser->handle($this->phone, $this->password, (string) request()->ip());
        } catch (ValidationException $exception) {
            $this->reset('password');

            throw $exception;
        }

        Auth::login($user);
        Session::regenerate();

        if ($user->hasPanelRole()) {
            $this->redirect(Dashboard::getUrl(panel: 'admin'));

            return;
        }

        $this->redirectIntended(route('dashboard'));
    }

    public function render(): View
    {
        return view('livewire.auth.login');
    }
}
