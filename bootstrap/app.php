<?php

declare(strict_types=1);

use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsurePanelRoleTwoFactor;
use App\Http\Middleware\EnsureSessionEpoch;
use App\Http\Middleware\RunMonitoringWatchdog;
use App\Http\Middleware\TrustProxies;
use App\Models\User;
use App\Services\ErrorCounter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\TrustProxies as BaseTrustProxies;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // عنوان الزائر الحقيقي خلف Laravel Cloud/Cloudflare (config/trustedproxy.php).
        $middleware->replace(BaseTrustProxies::class, TrustProxies::class);

        // عامة لا ضمن web، فتشمل لوحة Filament التي تبني مجموعتها الخاصة.
        $middleware->append(AddSecurityHeaders::class);

        $middleware->web(append: [
            AuthenticateSession::class,
            EnsureSessionEpoch::class,
            EnsurePanelRoleTwoFactor::class,
            RunMonitoringWatchdog::class,
        ]);

        $middleware->redirectUsersTo(
            fn (Request $request): string => $request->user() instanceof User
                ? $request->user()->homeUrl()
                : route('dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // عدّ الأخطاء المُبلَّغ عنها لصفحة "صحة النظام" وتنبيه الارتفاع المفاجئ، دون إيقاف تسجيلها.
        $exceptions->report(function (Throwable $exception): void {
            app(ErrorCounter::class)->record();
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
