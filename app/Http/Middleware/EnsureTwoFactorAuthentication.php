<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\TwoFactorSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * التحقق بخطوتين إلزامي لكل مسارات منطقة الإدارة (docs/DECISIONS.md):
 * - من لا يملك دور اللوحة يحصل على 404.
 * - جلسة دور لوحة بلا تحقق مفعَّل، أو لصاحب تحقق مفعَّل لم تجتز الخطوة الثانية،
 *   تُنهى ويُعاد إلى /login (يشمل طلبات Livewire، فهي دائمة). الإعداد لا يكون
 *   داخل اللوحة، بل من رابط الدعوة أو رابط admin:reset-2fa قبل أي وصول (T20).
 */
class EnsureTwoFactorAuthentication
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User && $user->is_active && $user->hasPanelRole(), 404);

        if (! TwoFactorSession::allowsAccess($user)) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login');
        }

        return $next($request);
    }
}
