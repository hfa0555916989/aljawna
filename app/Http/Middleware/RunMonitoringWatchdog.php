<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SystemAlerts;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * فحص المراقبة بعد إرسال الاستجابة، مرة كل monitoring.watchdog_seconds على الأكثر.
 * المجدول لا يستطيع التنبيه عن توقفه هو، فتكشفه طلبات الزوار (docs/DECISIONS.md).
 */
class RunMonitoringWatchdog
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $interval = (int) config('monitoring.watchdog_seconds');

        if ($interval <= 0) {
            return;
        }

        try {
            if (Cache::add('monitoring:watchdog', true, $interval)) {
                app(SystemAlerts::class)->check();
            }
        } catch (Throwable $exception) {
            Log::warning('Monitoring watchdog skipped.', ['exception' => $exception::class]);
        }
    }
}
