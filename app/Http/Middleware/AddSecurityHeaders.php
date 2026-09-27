<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * رؤوس الأمان وسياسة أمان المحتوى (docs/SPEC.md §12.1, §12.13) لكل استجابة.
 *
 * الموقع العام: السكربت من نطاق الموقع أو بـ nonce لكل طلب، فلا يُنفَّذ سكربت
 * مضمَّن لم يضعه القالب نفسه. لوحة Filament لا تضع nonce على كل سكربتاتها
 * المضمَّنة، فتسمح بالمضمَّن دون أي مصدر خارجي. 'unsafe-eval' لازمة في الاثنين
 * لأن Alpine وLivewire يقيّمان تعابير الواجهة بها (بديلها وضع CSP في Livewire
 * لا تدعمه تعابير Filament). الأنماط المضمَّنة مسموحة لأن Livewire وAlpine
 * يعتمدان عليها. Turnstile (challenges.cloudflare.com) هو المصدر الخارجي الوحيد.
 *
 * رأس محدد مسبقًا في الاستجابة لا يُستبدل (مثل سياسة الإيصالات الأضيق في
 * App\Services\ReceiptStorage). HSTS على الطلبات الآمنة خارج البيئة المحلية.
 */
class AddSecurityHeaders
{
    private const string TURNSTILE_ORIGIN = 'https://challenges.cloudflare.com';

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = null;

        if (! $this->isAdminArea($request)) {
            $nonce = Str::random(40);
            Vite::useCspNonce($nonce);
        }

        $response = $next($request);

        $headers = [
            config('security.headers.csp_report_only') ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy' => $this->contentSecurityPolicy($nonce),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ];

        $hstsMaxAge = (int) config('security.headers.hsts_max_age');

        if ($hstsMaxAge > 0 && $request->isSecure() && ! app()->isLocal()) {
            $headers['Strict-Transport-Security'] = 'max-age='.$hstsMaxAge
                .(config('security.headers.hsts_include_subdomains') ? '; includeSubDomains' : '');
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * لوحة Filament ومسارات الإدارة خارجها تحت مسار اللوحة (ADMIN_PATH).
     */
    private function isAdminArea(Request $request): bool
    {
        $path = (string) config('admin.path');

        return $request->is($path, $path.'/*');
    }

    private function contentSecurityPolicy(?string $nonce): string
    {
        $hot = $this->viteDevServerOrigin();
        $hotSocket = $hot === null ? null : (preg_replace('/^http/', 'ws', $hot) ?? $hot);
        $media = $this->publicMediaOrigin();

        $scripts = ["'self'", "'unsafe-eval'", self::TURNSTILE_ORIGIN, $hot];
        $scripts[] = $nonce === null ? "'unsafe-inline'" : "'nonce-{$nonce}'";

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => $scripts,
            'style-src' => ["'self'", "'unsafe-inline'", $hot],
            'img-src' => ["'self'", 'data:', 'blob:', $media],
            'font-src' => ["'self'", 'data:', $hot],
            'connect-src' => ["'self'", $hot, $hotSocket],
            'frame-src' => [self::TURNSTILE_ORIGIN],
            'media-src' => ["'self'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)
            ->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', array_unique(array_filter($sources))))
            ->implode('; ');
    }

    /**
     * أصل قرص الصور العام إن كان خارج نطاق الموقع (تخزين كائنات للشعار وصور الصفحات).
     */
    private function publicMediaOrigin(): ?string
    {
        try {
            $url = Storage::disk((string) config('security.branding.disk'))->url('csp');
        } catch (Throwable) {
            return null;
        }

        return $this->externalOrigin($url);
    }

    /**
     * خادم Vite المحلي أثناء التطوير فقط (npm run dev). عنوان IPv6 الحرفي ([::1])
     * لا تقبله CSP مصدرًا، فيُسمح بمخطط http: كله محليًا فقط.
     */
    private function viteDevServerOrigin(): ?string
    {
        if (! app()->isLocal() || ! Vite::isRunningHot()) {
            return null;
        }

        $origin = $this->externalOrigin(trim((string) file_get_contents(Vite::hotFile())));

        return $origin !== null && str_contains($origin, '://[') ? 'http:' : $origin;
    }

    private function externalOrigin(string $url): ?string
    {
        $parts = parse_url($url);

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host']) || ! in_array($parts['scheme'], ['http', 'https'], true)) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        return rtrim(url('/'), '/') === $origin ? null : $origin;
    }
}
