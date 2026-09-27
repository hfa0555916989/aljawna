<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TwoFactorSession;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * لا وصول لدور اللوحة إلى أي مسار في الموقع قبل التحقق بخطوتين (docs/DECISIONS.md، T20):
 * جلسة مشرف أو مدير بلا تحقق مفعَّل، أو لم تجتز خطوته الثانية، تُنهى فورًا
 * ويُعاد إلى /login. منطقة الإدارة تفرض الشيء نفسه (EnsureTwoFactorAuthentication).
 */
class EnsurePanelRoleTwoFactor
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $request->hasSession() && ! TwoFactorSession::allowsAccess($user)) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            throw new AuthenticationException('Unauthenticated.', [Auth::getDefaultDriver()], route('login'));
        }

        return $next($request);
    }
}
