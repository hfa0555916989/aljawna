<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;

/**
 * مصادقة لوحة الإدارة مع الدخول الموحّد (docs/DECISIONS.md):
 * الزائر يُعاد إلى /login، ومن لا يملك دور اللوحة (المبادر، أو أي حساب معطَّل)
 * يحصل على 404 لا 403، فلا تكشف اللوحة وجودها لمن ليس له بها شأن.
 */
class AuthenticateAdminPanel extends Authenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $guard = Filament::auth();

        if (! $guard->check()) {
            $this->unauthenticated($request, $guards);

            return; /** @phpstan-ignore-line */
        }

        $this->auth->shouldUse(Filament::getAuthGuard());

        $user = $guard->user();

        abort_unless(
            $user instanceof User && $user->canAccessPanel(Filament::getPanel('admin')),
            404,
        );
    }

    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
