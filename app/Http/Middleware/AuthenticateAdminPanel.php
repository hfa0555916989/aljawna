<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * مصادقة لوحة الإدارة: المبادر (دور user) الفعّال يُحوَّل إلى لوحته /dashboard،
 * وغيره يمرّ بفحص Filament (canAccessPanel أو 403) (T04).
 */
class AuthenticateAdminPanel extends Authenticate
{
    /**
     * @param  array<string>  $guards
     */
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && $user->role === UserRole::User && $user->is_active) {
            throw new HttpResponseException(redirect()->route('dashboard'));
        }

        parent::authenticate($request, $guards);
    }
}
