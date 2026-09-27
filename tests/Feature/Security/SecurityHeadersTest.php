<?php

declare(strict_types=1);

use App\Models\Transfer;
use App\Models\User;
use App\Services\ReceiptStorage;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| رؤوس الأمان وسياسة أمان المحتوى (docs/SPEC.md §12.1, §12.13، T20)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

/**
 * @return array<string, string>
 */
function cspDirectives(string $policy): array
{
    return collect(explode(';', $policy))
        ->map(fn (string $directive): string => trim($directive))
        ->filter()
        ->mapWithKeys(fn (string $directive): array => [strtok($directive, ' ') => $directive])
        ->all();
}

test('الصفحات العامة تحمل سياسة بـ nonce لكل طلب، ولا تسمح بسكربت مضمَّن بلا nonce', function (): void {
    $first = $this->get('/')->assertOk();
    $second = $this->get('/')->assertOk();

    $policy = (string) $first->headers->get('Content-Security-Policy');
    $directives = cspDirectives($policy);

    preg_match("/'nonce-([^']+)'/", $policy, $nonce);

    expect($nonce[1] ?? null)->not->toBeNull()
        ->and($second->headers->get('Content-Security-Policy'))->not->toContain($nonce[1])
        ->and($directives['script-src'])->not->toContain("'unsafe-inline'")
        ->and($directives['default-src'])->toBe("default-src 'self'")
        ->and($directives['object-src'])->toBe("object-src 'none'")
        ->and($directives['base-uri'])->toBe("base-uri 'self'")
        ->and($directives['form-action'])->toBe("form-action 'self'")
        ->and($directives['frame-ancestors'])->toBe("frame-ancestors 'none'")
        ->and($directives['frame-src'])->toBe('frame-src https://challenges.cloudflare.com');

    $html = (string) $first->getContent();

    preg_match_all('/<script\b[^>]*>/i', $html, $scripts);

    expect($scripts[0])->not->toBeEmpty();

    foreach ($scripts[0] as $tag) {
        expect($tag)->toContain('nonce="'.$nonce[1].'"');
    }
});

test('لا مصدر سكربت خارجي إلا Turnstile', function (): void {
    $policy = (string) $this->get('/login')->headers->get('Content-Security-Policy');

    preg_match_all('#https?://[^\s;]+#', cspDirectives($policy)['script-src'], $origins);

    expect($origins[0])->toBe(['https://challenges.cloudflare.com']);
});

test('لوحة الإدارة تحمل سياستها بلا أي مصدر خارجي للسكربت', function (): void {
    $response = $this->actingAs(User::factory()->admin()->create())->get(adminPath())->assertOk();
    $directives = cspDirectives((string) $response->headers->get('Content-Security-Policy'));

    expect($directives['script-src'])->not->toContain('nonce-')
        ->and($directives['frame-ancestors'])->toBe("frame-ancestors 'none'")
        ->and($directives['object-src'])->toBe("object-src 'none'");

    preg_match_all('#https?://[^\s;]+#', $directives['script-src'], $origins);

    expect($origins[0])->toBe(['https://challenges.cloudflare.com']);
});

test('رؤوس الأمان الثابتة على الصفحات العامة ولوحة الإدارة وصفحات الخطأ', function (string $path, bool $asAdmin): void {
    if ($asAdmin) {
        $this->actingAs(User::factory()->admin()->create());
    }

    $response = $this->get($path === 'panel' ? adminPath() : $path);

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($response->headers->get('Cross-Origin-Opener-Policy'))->toBe('same-origin')
        ->and($response->headers->get('Permissions-Policy'))->toContain('camera=()')
        ->and($response->headers->get('Content-Security-Policy'))->not->toBeNull();
})->with([
    'الرئيسية' => ['/', false],
    'الدخول' => ['/login', false],
    'اللوحة' => ['panel', true],
    'صفحة غير موجودة' => ['/no-such-page-here', false],
]);

test('HSTS على الطلبات الآمنة فقط، بلا includeSubDomains افتراضيًا، وقابل للتعطيل', function (): void {
    expect($this->get('/')->headers->has('Strict-Transport-Security'))->toBeFalse();

    expect($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000');

    config(['security.headers.hsts_include_subdomains' => true]);

    expect($this->get('https://localhost/')->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');

    config(['security.headers.hsts_max_age' => 0]);

    expect($this->get('https://localhost/')->headers->has('Strict-Transport-Security'))->toBeFalse();
});

test('وضع المراقبة يرسل السياسة دون فرضها', function (): void {
    config(['security.headers.csp_report_only' => true]);

    $response = $this->get('/');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($response->headers->get('Content-Security-Policy-Report-Only'))->toContain("'nonce-");
});

test('أصل تخزين الصور العام الخارجي يُسمح في img-src فقط', function (): void {
    config([
        'filesystems.disks.csp-media' => ['driver' => 'local', 'root' => storage_path('app/csp-media'), 'url' => 'https://media.example.test/brand'],
        'security.branding.disk' => 'csp-media',
    ]);

    $directives = cspDirectives((string) $this->get('/')->headers->get('Content-Security-Policy'));

    expect($directives['img-src'])->toContain('https://media.example.test')
        ->and($directives['script-src'])->not->toContain('media.example.test')
        ->and($directives['default-src'])->not->toContain('media.example.test');
});

test('سياسة الإيصال الأضيق لا تُستبدل', function (): void {
    Storage::fake((string) config('security.receipts.disk'));
    $transfer = Transfer::factory()->create();
    Storage::disk((string) config('security.receipts.disk'))->put($transfer->receipt_path, pngBytes());

    $response = $this->actingAs($transfer->user)
        ->get(app(ReceiptStorage::class)->temporaryUrl($transfer))
        ->assertOk();

    expect($response->headers->get('Content-Security-Policy'))->toStartWith("default-src 'none'")
        ->and($response->headers->get('Referrer-Policy'))->toBe('no-referrer');
});
