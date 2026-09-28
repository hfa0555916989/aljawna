<?php

declare(strict_types=1);

use App\HealthStatus;
use App\Models\SystemHeartbeat;
use App\Models\Transfer;
use App\Services\ReceiptBackup;
use App\Services\SystemAlerts;
use App\Services\SystemHealth;
use App\Support\BackupCipher;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| نسخ الإيصالات الاحتياطي المشفّر كل ساعة واسترجاعها (T21)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('receipts');
    Storage::fake('backups');
    config([
        'backup.encryption_key' => BackupCipher::generateKey(),
        'monitoring.backup.enabled' => true,
    ]);
});

function transferWithReceiptFile(string $contents): Transfer
{
    $transfer = Transfer::factory()->create();
    Storage::disk('receipts')->put((string) $transfer->receipt_path, $contents);

    return $transfer;
}

test('ينسخ الإيصالات مشفّرة، ثم الجديدة فقط في كل تشغيل', function (): void {
    $first = transferWithReceiptFile(jpegBytes());
    $second = transferWithReceiptFile(pdfBytes('second'));
    $backups = app(ReceiptBackup::class);

    $this->artisan('backup:receipts')->expectsOutputToContain('نُسخ 2')->assertSuccessful();

    foreach ([$first, $second] as $transfer) {
        $copy = (string) Storage::disk('backups')->get($backups->backupPath((string) $transfer->receipt_path));
        expect($copy)->toStartWith(BackupCipher::MAGIC)
            ->and($copy)->not->toContain((string) Storage::disk('receipts')->get((string) $transfer->receipt_path));
    }

    $this->artisan('backup:receipts')->expectsOutputToContain('نُسخ 0')->assertSuccessful();

    transferWithReceiptFile(pngBytes());
    $this->artisan('backup:receipts')->expectsOutputToContain('نُسخ 1')->assertSuccessful();

    expect(Storage::disk('backups')->allFiles('receipts'))->toHaveCount(3)
        ->and(app(SystemHealth::class)->receiptsBackup()['status'])->toBe(HealthStatus::Ok);
});

test('لا يُنسخ إيصال حُذفت صورته بسياسة الاحتفاظ', function (): void {
    Transfer::factory()->create(['receipt_path' => null, 'receipt_purged_at' => now()]);

    $this->artisan('backup:receipts')->expectsOutputToContain('نُسخ 0')->assertSuccessful();

    expect(Storage::disk('backups')->allFiles())->toBe([]);
});

test('الإيصال المفقود من التخزين يُسترجع من نسخته كما كان', function (): void {
    $original = jpegBytes(80, 50);
    $transfer = transferWithReceiptFile($original);
    $kept = transferWithReceiptFile(pdfBytes('kept'));
    $this->artisan('backup:receipts')->assertSuccessful();

    Storage::disk('receipts')->delete((string) $transfer->receipt_path);

    $this->artisan('backup:restore-receipts', ['--force' => true])
        ->expectsOutputToContain('استُرجع 1')
        ->assertSuccessful();

    expect(Storage::disk('receipts')->get((string) $transfer->receipt_path))->toBe($original)
        ->and(Storage::disk('receipts')->get((string) $kept->receipt_path))->toBe(pdfBytes('kept'));
});

test('فشل ملف واحد يُسجَّل فشلًا في صحة النظام ويطلق التنبيه، وتُنسخ البقية', function (): void {
    transferWithReceiptFile(jpegBytes());
    Transfer::factory()->create();

    $this->artisan('backup:receipts')->expectsOutputToContain('فشل 1')->assertFailed();

    expect(Storage::disk('backups')->allFiles('receipts'))->toHaveCount(1)
        ->and(SystemHeartbeat::named(SystemHeartbeat::RECEIPTS_BACKUP)?->status)->toBe(SystemHeartbeat::BACKUP_FAILED)
        ->and(app(SystemAlerts::class)->currentProblems())->toContain(SystemAlerts::BACKUP_FAILED);
});

test('بلا مفتاح تشفير لا يُرفع شيء ويُسجَّل الفشل', function (): void {
    transferWithReceiptFile(jpegBytes());
    config(['backup.encryption_key' => null]);

    $this->artisan('backup:receipts')->expectsOutputToContain('BACKUP_ENCRYPTION_KEY')->assertFailed();

    expect(Storage::disk('backups')->allFiles())->toBe([])
        ->and(app(SystemHealth::class)->receiptsBackup()['status'])->toBe(HealthStatus::Failing);
});

test('غير المفعَّل لا ينسخ شيئًا ولا يسجّل نبضًا', function (): void {
    config(['monitoring.backup.enabled' => false]);
    transferWithReceiptFile(jpegBytes());

    $this->artisan('backup:receipts')->assertSuccessful();

    expect(Storage::disk('backups')->allFiles())->toBe([])
        ->and(SystemHeartbeat::named(SystemHeartbeat::RECEIPTS_BACKUP))->toBeNull();
});

test('التأخر أكثر من 3 ساعات (النسخ كل ساعة) يُعد فشلًا', function (): void {
    SystemHeartbeat::recordReceiptsBackup(true);
    $this->travel(2)->hours();
    expect(app(SystemHealth::class)->receiptsBackup()['status'])->toBe(HealthStatus::Ok);

    $this->travel(2)->hours();
    expect(app(SystemHealth::class)->receiptsBackup()['status'])->toBe(HealthStatus::Failing);
});

test('المجدول يشغّل النسخ والحذف بعد مدة الاحتفاظ', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('backup:receipts')
        ->expectsOutputToContain('backup:database')
        ->expectsOutputToContain('receipts:purge-expired')
        ->assertSuccessful();
});
