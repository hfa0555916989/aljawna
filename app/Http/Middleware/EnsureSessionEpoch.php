<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\SessionEpoch;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * يُخرج الجلسة إن رُفع جيلها بعد الدخول، كما بعد تعديل رقم الدخول (docs/SPEC.md §4.2).
 */
class EnsureSessionEpoch
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $request->hasSession()) {
            return $next($request);
        }

        $stored = $request->session()->get(SessionEpoch::SESSION_KEY);
        $storedValue = $stored === null ? 0 : (int) $stored;

        if ($storedValue !== SessionEpoch::current((int) $user->getKey())) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException(
                'Unauthenticated.',
                [Auth::getDefaultDriver()],
                route('login'),
            );
        }

        return $next($request);
    }
}
