<?php

declare(strict_types=1);

use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Models\User;
use App\Support\PersonalDataRedactor;
use App\Support\SaudiIban;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Monolog\LogRecord;

/*
|--------------------------------------------------------------------------
| لا جوالات ولا آيبانات في السجلات (T20 — tasks/T20-qa-hardening.md #5)
|--------------------------------------------------------------------------
*/

/**
 * قناة تلتقط كل ما يُكتب، بإعداد monolog كقناة Laravel Cloud (تُعرَّف عند الإقلاع).
 */
function captureLogs(): TestHandler
{
    config([
        'logging.channels.capture' => ['driver' => 'monolog', 'handler' => TestHandler::class],
        'logging.default' => 'capture',
    ]);
    Log::forgetChannel('capture');

    /** @var Logger $monolog */
    $monolog = Log::channel('capture')->getLogger();

    /** @var TestHandler */
    return $monolog->getHandlers()[0];
}

function capturedText(TestHandler $handler): string
{
    return collect($handler->getRecords())
        ->map(fn (LogRecord $record): string => $record->message.' '.json_encode($record->context, JSON_UNESCAPED_UNICODE).' '
            .(($record->context['exception'] ?? null) instanceof Throwable ? $record->context['exception']->getMessage() : ''))
        ->implode("\n");
}

test('تُحجب الجوالات بكل صيغها والآيبانات من الرسالة والسياق بكل عمقه', function (): void {
    $handler = captureLogs();
    $iban = SaudiIban::fromParts('80', '000000123456789012');

    Log::warning('Contact +966512345678 or 0512345678 or 00966 512345678 or ٠٥١٢٣٤٥٦٧٨', [
        'phone' => '966512345678',
        'nested' => ['iban' => $iban, 'grouped' => SaudiIban::grouped($iban), 'list' => ['051-234-5678']],
        'count' => 42,
    ]);

    $text = capturedText($handler);

    expect($text)->not->toContain('512345678')
        ->not->toContain('٥١٢٣٤٥٦٧٨')
        ->not->toContain($iban)
        ->not->toContain(SaudiIban::grouped($iban))
        ->not->toContain('234-5678')
        ->toContain(PersonalDataRedactor::PHONE_MASK)
        ->toContain(PersonalDataRedactor::IBAN_MASK)
        ->toContain('"count":42');
});

test('أرقام أخرى لا تُحجب خطأً: الأوقات والمبالغ والمعرّفات القصيرة', function (): void {
    expect(PersonalDataRedactor::redact('ts 1790000000, amount 45000.00, id 12345, port 5432'))
        ->toBe('ts 1790000000, amount 45000.00, id 12345, port 5432');
});

test('رسالة استثناء قاعدة البيانات عند تكرار الجوال لا تكشفه ولا أي قيمة معاملة في السجل', function (): void {
    $handler = captureLogs();
    User::factory()->create(['phone' => '+966512345678']);

    try {
        User::factory()->create(['phone' => '+966512345678']);
    } catch (QueryException $exception) {
        report($exception);
    }

    $text = capturedText($handler);

    expect($handler->getRecords())->not->toBeEmpty()
        ->and($text)->not->toContain('512345678')
        ->toContain('users_phone_unique')
        ->toContain('insert into "users"')
        // لا قيم معاملات أخرى: لا اسم ولا تجزئة كلمة المرور.
        ->not->toContain('$2y$');
});

test('كل قناة سجل تحمل الحجب، ومنها المجمَّعة وقنوات الملفات', function (): void {
    $path = storage_path('logs/privacy-'.uniqid().'.log');

    config([
        'logging.channels.privacy-file' => ['driver' => 'single', 'path' => $path],
        'logging.channels.privacy-stack' => ['driver' => 'stack', 'channels' => ['privacy-file']],
    ]);

    Log::channel('privacy-stack')->error('failed for 0512345678', ['exception' => new RuntimeException('user +966512345678')]);

    $written = File::get($path);
    File::delete($path);

    expect($written)->toContain('failed for [phone]')
        ->not->toContain('512345678');
});

test('مسارات الدخول والاستعادة الفاشلة لا تكتب جوالًا في السجل', function (): void {
    $handler = captureLogs();
    User::factory()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    Livewire::test(Login::class)->set('phone', '0512345678')->set('password', 'wrong')->call('login');
    Livewire::test(ForgotPassword::class)->set('phone', '0599999999')->call('submit');

    expect(capturedText($handler))->not->toContain('512345678')->not->toContain('599999999');
});

test('مسار الاستثناء في السجلات بلا قيم المعاملات خارج التطوير المحلي', function (): void {
    expect(ini_get('zend.exception_ignore_args'))->toBe('1');
});
