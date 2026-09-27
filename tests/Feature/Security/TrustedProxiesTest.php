<?php

declare(strict_types=1);

use App\Models\LoginAttempt;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| عنوان الزائر الحقيقي خلف Laravel Cloud (config/trustedproxy.php، T20)
|--------------------------------------------------------------------------
| الطلبات هنا تمرّ بكامل مكدّس HTTP (لا Livewire::test الذي يتخطى الوسطاء)،
| فتثبت أن القفل المؤقت وسجل المحاولات يعملان على عنوان الزائر لا الوسيط.
*/

const EDGE_PROXY_IP = '10.20.30.40';

beforeEach(function (): void {
    unset($_SERVER['LARAVEL_CLOUD'], $_ENV['LARAVEL_CLOUD']);
});

afterEach(function (): void {
    unset($_SERVER['LARAVEL_CLOUD'], $_ENV['LARAVEL_CLOUD']);
});

/**
 * محاولة دخول كاملة عبر HTTP: فتح /login ثم إرسال النموذج إلى مسار تحديث Livewire.
 *
 * @param  array<string, string>  $headers
 */
function loginOverHttp(string $phone, string $password, array $headers): TestResponse
{
    $server = ['REMOTE_ADDR' => EDGE_PROXY_IP];

    $page = test()->withServerVariables($server)->withHeaders($headers)->get('/login')->assertOk();

    preg_match('/wire:snapshot="([^"]+)"/', (string) $page->getContent(), $matches);
    $snapshot = html_entity_decode($matches[1] ?? '', ENT_QUOTES);

    expect($snapshot)->not->toBe('');

    return test()->withServerVariables($server)
        ->withHeaders([...$headers, 'X-Livewire' => 'true'])
        ->postJson(app('livewire')->getUpdateUri(), [
            'components' => [[
                'snapshot' => $snapshot,
                'updates' => ['phone' => $phone, 'password' => $password],
                'calls' => [['path' => '', 'method' => 'login', 'params' => []]],
            ]],
        ]);
}

function renderedHtml(TestResponse $response): string
{
    return (string) $response->assertOk()->json('components.0.effects.html');
}

test('على Laravel Cloud يُسجَّل عنوان الزائر من CF-Connecting-IP لا عنوان الوسيط ولا أول X-Forwarded-For المزوَّر', function (): void {
    $_SERVER['LARAVEL_CLOUD'] = '1';

    loginOverHttp('0500000001', 'wrong-password', [
        'X-Forwarded-For' => '6.6.6.6, 203.0.113.7',
        'CF-Connecting-IP' => '203.0.113.7',
    ])->assertOk();

    expect(LoginAttempt::query()->sole()->ip)->toBe('203.0.113.7');
});

test('القفل المؤقت لكل عنوان زائر: تغيير X-Forwarded-For لا يفلت منه، وزائر آخر خلف نفس الوسيط لا يُقفل', function (): void {
    $_SERVER['LARAVEL_CLOUD'] = '1';
    $user = User::factory()->create();

    foreach (range(1, (int) config('security.login.max_attempts')) as $attempt) {
        loginOverHttp('05000000'.str_pad((string) $attempt, 2, '0', STR_PAD_LEFT), 'wrong-password', [
            'X-Forwarded-For' => "198.51.100.{$attempt}, 203.0.113.7",
            'CF-Connecting-IP' => '203.0.113.7',
        ])->assertOk();
    }

    $locked = loginOverHttp($user->phone, 'password', [
        'X-Forwarded-For' => '198.51.100.99, 203.0.113.7',
        'CF-Connecting-IP' => '203.0.113.7',
    ]);

    expect(renderedHtml($locked))->toContain('أُوقف الدخول مؤقتًا')
        ->and(LoginAttempt::query()->where('succeeded', true)->exists())->toBeFalse();

    loginOverHttp($user->phone, 'password', [
        'X-Forwarded-For' => '203.0.113.8',
        'CF-Connecting-IP' => '203.0.113.8',
    ])->assertOk();

    expect(LoginAttempt::query()->where('succeeded', true)->sole()->ip)->toBe('203.0.113.8')
        ->and($user->fresh()->last_login_ip)->toBe('203.0.113.8');
});

test('TRUSTED_PROXIES الفارغ في .env يعني "غير مضبوط" فلا يعطّل الكشف التلقائي على Laravel Cloud', function (): void {
    $_ENV['TRUSTED_PROXIES'] = $_SERVER['TRUSTED_PROXIES'] = '';
    putenv('TRUSTED_PROXIES=');

    try {
        $config = require config_path('trustedproxy.php');
    } finally {
        unset($_ENV['TRUSTED_PROXIES'], $_SERVER['TRUSTED_PROXIES']);
        putenv('TRUSTED_PROXIES');
    }

    expect($config['proxies'])->toBeNull();

    $_SERVER['LARAVEL_CLOUD'] = '1';
    config(['trustedproxy.proxies' => $config['proxies']]);

    loginOverHttp('0500000001', 'wrong-password', ['CF-Connecting-IP' => '203.0.113.7'])->assertOk();

    expect(LoginAttempt::query()->sole()->ip)->toBe('203.0.113.7');
});

test('خارج Laravel Cloud وبلا وسطاء موثوقين تُتجاهل الترويسات ويُعتمد عنوان الاتصال نفسه', function (): void {
    loginOverHttp('0500000001', 'wrong-password', [
        'X-Forwarded-For' => '203.0.113.7',
        'CF-Connecting-IP' => '203.0.113.7',
    ])->assertOk();

    expect(LoginAttempt::query()->sole()->ip)->toBe(EDGE_PROXY_IP);
});

test('وسطاء محددون عبر TRUSTED_PROXIES: X-Forwarded-For يُقرأ من الوسيط الموثوق فقط', function (): void {
    config(['trustedproxy.proxies' => EDGE_PROXY_IP]);

    loginOverHttp('0500000001', 'wrong-password', ['X-Forwarded-For' => '6.6.6.6, 203.0.113.9'])->assertOk();

    expect(LoginAttempt::query()->sole()->ip)->toBe('203.0.113.9');
});

test('ترويسة عنوان الزائر قابلة للتخصيص ويمكن تعطيلها', function (): void {
    $_SERVER['LARAVEL_CLOUD'] = '1';
    config(['trustedproxy.client_ip_header' => 'True-Client-IP']);

    loginOverHttp('0500000001', 'wrong-password', [
        'X-Forwarded-For' => '203.0.113.1',
        'CF-Connecting-IP' => '203.0.113.2',
        'True-Client-IP' => '203.0.113.3',
    ])->assertOk();

    config(['trustedproxy.client_ip_header' => 'none']);

    loginOverHttp('0500000002', 'wrong-password', [
        'X-Forwarded-For' => '203.0.113.1',
        'CF-Connecting-IP' => '203.0.113.2',
    ])->assertOk();

    expect(LoginAttempt::query()->orderBy('id')->pluck('ip')->all())->toBe(['203.0.113.3', '203.0.113.1']);
});

test('قيمة غير صالحة في ترويسة الوسيط الطرفي لا تُعتمد عنوانًا', function (): void {
    $_SERVER['LARAVEL_CLOUD'] = '1';

    Route::get('/_test/client-ip', fn () => response(request()->ip()));

    $this->withServerVariables(['REMOTE_ADDR' => EDGE_PROXY_IP])
        ->withHeaders(['X-Forwarded-For' => '203.0.113.5', 'CF-Connecting-IP' => 'not-an-ip'])
        ->get('/_test/client-ip')
        ->assertSee('203.0.113.5');
});

test('الطلب عبر وسيط موثوق يُعدّ آمنًا من X-Forwarded-Proto فتُولَّد روابط https', function (): void {
    $_SERVER['LARAVEL_CLOUD'] = '1';

    Route::get('/_test/scheme', fn () => response(request()->isSecure() ? 'secure' : 'plain'));

    $this->withServerVariables(['REMOTE_ADDR' => EDGE_PROXY_IP])
        ->withHeaders(['X-Forwarded-Proto' => 'https', 'CF-Connecting-IP' => '203.0.113.5'])
        ->get('/_test/scheme')
        ->assertSee('secure');
});
