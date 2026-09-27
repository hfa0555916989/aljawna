<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * عنوان الزائر الحقيقي خلف الوسطاء (config/trustedproxy.php)، لتعمل عليه حدود
 * المحاولات والقفل المؤقت وسجلات الدخول والتدقيق، لا على عنوان الوسيط.
 *
 * على Laravel Cloud يمرّ كل طلب عبر Cloudflare، وهو يضيف عنوان الزائر إلى آخر
 * X-Forwarded-For ولا يحذف ما أرسله الزائر فيه، فالثقة بكل الوسطاء (*) تجعل
 * أوله المزوَّر هو عنوان الطلب. لذلك تُقدَّم ترويسة يضعها الوسيط الطرفي وحده
 * (CF-Connecting-IP)، ولا تُقرأ إلا من طلب جاء عبر وسيط موثوق.
 */
class TrustProxies extends Middleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): mixed
    {
        return parent::handle($request, function (Request $request) use ($next): mixed {
            $this->useEdgeClientIp($request);

            return $next($request);
        });
    }

    private function useEdgeClientIp(Request $request): void
    {
        $header = $this->clientIpHeader();

        if ($header === null || ! $request->isFromTrustedProxy()) {
            return;
        }

        $clientIp = trim((string) $request->headers->get($header));

        if (filter_var($clientIp, FILTER_VALIDATE_IP) === false) {
            return;
        }

        $request->headers->set('X-Forwarded-For', $clientIp);
    }

    private function clientIpHeader(): ?string
    {
        $configured = config('trustedproxy.client_ip_header');

        if (is_string($configured) && trim($configured) !== '') {
            return strtolower(trim($configured)) === 'none' ? null : trim($configured);
        }

        return laravel_cloud() ? 'CF-Connecting-IP' : null;
    }
}
