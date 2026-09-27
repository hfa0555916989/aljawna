<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TwoFactorSession;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * التحقق بخطوتين إلزامي لكل مسارات منطقة الإدارة (docs/DECISIONS.md):
 * - من لا يملك دور اللوحة يحصل على 404.
 * - من لم يفعّل التحقق بعد يُحوَّل إلى صفحة الإعداد الإلزامية في Filament،
 *   ولا يصل إلى أي صفحة أو إجراء آخر (يشمل طلبات Livewire، فهي دائمة).
 * - جلسة صاحب تحقق مفعَّل لم تجتز الخطوة الثانية تُنهى ويُعاد إلى /login.
 */
class EnsureTwoFactorAuthentication
{
    /**
     * مسارات اللوحة المتاحة قبل إعداد التحقق.
     *
     * @var list<string>
     */
    private const array SET_UP_ROUTES = [
        'filament.admin.auth.multi-factor-authentication.set-up-required',
        'filament.admin.auth.logout',
    ];

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active && $user->hasPanelRole(), 404);

        if (TwoFactorSession::isUnverified($user)) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        if (! $user->hasTwoFactorEnabled() && ! in_array($request->route()?->getName(), self::SET_UP_ROUTES, true)) {
            return redirect()->guest((string) Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl());
        }

        return $next($request);
    }
}
