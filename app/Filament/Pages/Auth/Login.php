<?php

declare(strict_types=1);

namespace App\Filament\Pages\Auth;

use App\Actions\Auth\LoginUser;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

/**
 * دخول لوحة الإدارة بالجوال وكلمة المرور (docs/SPEC.md §3, T04).
 * يعيد استخدام LoginUser، فيسري تحديد المحاولات وسجل login_attempts
 * والرسالة الموحّدة ومنع الحساب المعطَّل كما في /login. المبادر يُحوَّل إلى /dashboard.
 */
class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        /** @var array{phone: string, password: string} $data */
        $data = $this->form->getState();

        try {
            $user = app(LoginUser::class)->handle($data['phone'], $data['password'], (string) request()->ip());
        } catch (ValidationException $exception) {
            $this->form->fill(['phone' => $data['phone']]);

            throw ValidationException::withMessages([
                'data.phone' => $exception->errors()['phone'] ?? [__('auth.failed')],
            ]);
        }

        Filament::auth()->login($user);
        session()->regenerate();

        if (! $this->isUserAllowedToAccessPanel($user)) {
            $this->redirectRoute('dashboard');

            return null;
        }

        return app(LoginResponse::class);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getPhoneFormComponent(),
                $this->getPasswordFormComponent(),
            ]);
    }

    protected function getPhoneFormComponent(): Component
    {
        return TextInput::make('phone')
            ->label(__('admin.login.phone'))
            ->type('tel')
            ->required()
            ->maxLength(32)
            ->autocomplete('tel')
            ->autofocus()
            ->placeholder('05XXXXXXXX')
            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'tel']);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label(__('admin.login.password'))
            ->password()
            ->revealable()
            ->required()
            ->maxLength(255)
            ->autocomplete('current-password');
    }
}
