<?php

declare(strict_types=1);

use App\Exceptions\BackupException;
use App\Support\BackupCipher;

/*
|--------------------------------------------------------------------------
| تشفير النسخ الاحتياطية (T21): يسترجع المحتوى كما هو، ويرفض المفتاح الخاطئ
| والتعديل والاقتطاع.
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['backup.encryption_key' => BackupCipher::generateKey()]);
});

/**
 * @return resource
 */
function memoryStream(string $contents = '')
{
    $stream = fopen('php://memory', 'w+b');
    fwrite($stream, $contents);
    rewind($stream);

    return $stream;
}

function encryptString(string $plain): string
{
    $output = memoryStream();
    BackupCipher::fromConfig()->encryptStream(memoryStream($plain), $output);
    rewind($output);

    return (string) stream_get_contents($output);
}

function decryptString(string $encrypted): string
{
    $output = memoryStream();
    BackupCipher::fromConfig()->decryptStream(memoryStream($encrypted), $output);
    rewind($output);

    return (string) stream_get_contents($output);
}

test('يسترجع المحتوى كما هو، فارغًا وصغيرًا وأكبر من جزء واحد', function (string $plain): void {
    $encrypted = encryptString($plain);

    expect(decryptString($encrypted))->toBe($plain);

    if ($plain !== '') {
        expect($encrypted)->not->toContain($plain);
    }
})->with([
    'فارغ' => [''],
    'صغير' => ['إيصال حوالة +966500000001'],
    'عدة أجزاء' => [random_bytes(BackupCipher::CHUNK_BYTES * 2 + 123)],
    'مضاعف الجزء بالضبط' => [random_bytes(BackupCipher::CHUNK_BYTES * 2)],
]);

test('تشفير المحتوى نفسه مرتين يعطي ناتجين مختلفين', function (): void {
    expect(encryptString('نفس المحتوى'))->not->toBe(encryptString('نفس المحتوى'));
});

test('المفتاح الخاطئ يُرفض برسالة واضحة', function (): void {
    $encrypted = encryptString('سري');
    config(['backup.encryption_key' => BackupCipher::generateKey()]);

    expect(fn () => decryptString($encrypted))->toThrow(BackupException::class, 'بمفتاح غير');
});

test('تعديل أي بايت في المحتوى المشفّر يُكتشف', function (): void {
    $encrypted = encryptString(str_repeat('بيانات ', 50));
    $tampered = substr_replace($encrypted, chr(ord($encrypted[60]) ^ 1), 60, 1);

    expect(fn () => decryptString($tampered))->toThrow(BackupException::class);
});

test('النسخة المقتطعة أو الزائدة تُرفض', function (): void {
    $plain = random_bytes(BackupCipher::CHUNK_BYTES + 10);
    $encrypted = encryptString($plain);
    $firstChunkEnd = strlen(BackupCipher::MAGIC) + BackupCipher::KEY_ID_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES
        + 4 + BackupCipher::CHUNK_BYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES;

    expect(fn () => decryptString(substr($encrypted, 0, $firstChunkEnd)))->toThrow(BackupException::class, 'ناقصة')
        ->and(fn () => decryptString(substr($encrypted, 0, -5)))->toThrow(BackupException::class)
        ->and(fn () => decryptString($encrypted.'x'))->toThrow(BackupException::class);
});

test('ملف ليس نسخة من النظام يُرفض', function (): void {
    expect(fn () => decryptString(str_repeat('not a backup ', 10)))->toThrow(BackupException::class, 'ليس نسخة');
});

test('المفتاح يجب أن يكون مضبوطًا وبالصيغة الصحيحة', function (?string $key, string $message): void {
    config(['backup.encryption_key' => $key]);

    expect(fn () => BackupCipher::fromConfig())->toThrow(BackupException::class, $message);
})->with([
    'فارغ' => [null, 'غير مضبوط'],
    'بلا base64:' => [base64_encode(random_bytes(32)), 'base64:'],
    'قصير' => ['base64:'.base64_encode(random_bytes(16)), 'base64:'],
]);
