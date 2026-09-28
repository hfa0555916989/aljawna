<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\BackupException;
use App\Models\Transfer;
use App\Support\BackupCipher;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * نسخ الإيصالات الاحتياطي المشفّر خارج Laravel Cloud (T21، docs/RUNBOOK.md).
 *
 * كل ساعة: الإيصالات التي لا نسخة لها بعد فقط، كل إيصال في ملف مشفّر مستقل
 * باسم "{prefix}/{receipt_path}.enc"، فيُحذف بعينه عند انتهاء مدة الاحتفاظ
 * (App\Services\ReceiptRetention). المصدر جدول الحوالات لا قائمة ملفات القرص،
 * فلا يُنسخ ملف يتيم ولا يُنسخ إيصال حُذفت صورته.
 *
 * لا يُسجَّل في السجلات إلا رقم الحوالة ونوع الخطأ.
 */
class ReceiptBackup
{
    public function __construct(private ReceiptStorage $receipts) {}

    /**
     * @return array{uploaded: int, failed: int}
     *
     * @throws BackupException
     */
    public function run(): array
    {
        $cipher = BackupCipher::fromConfig();
        $existing = array_flip($this->disk()->allFiles($this->prefix()));
        $uploaded = 0;
        $failed = 0;

        Transfer::query()
            ->whereNotNull('receipt_path')
            ->select(['id', 'receipt_path'])
            ->lazyById(500)
            ->each(function (Transfer $transfer) use ($cipher, $existing, &$uploaded, &$failed): void {
                $receiptPath = (string) $transfer->receipt_path;
                $target = $this->backupPath($receiptPath);

                if (isset($existing[$target])) {
                    return;
                }

                try {
                    $this->upload($cipher, $receiptPath, $target);
                    $uploaded++;
                } catch (Throwable $exception) {
                    $failed++;
                    Log::error('Receipt backup failed.', ['transfer_id' => $transfer->id, 'exception' => $exception::class]);
                }
            });

        return ['uploaded' => $uploaded, 'failed' => $failed];
    }

    /**
     * يعيد الإيصالات المفقودة من التخزين (أو كلها مع $overwrite) من نسخها المشفّرة.
     *
     * @return array{restored: int, skipped: int, missing: int, failed: int}
     *
     * @throws BackupException
     */
    public function restore(bool $overwrite = false): array
    {
        $cipher = BackupCipher::fromConfig();
        $counts = ['restored' => 0, 'skipped' => 0, 'missing' => 0, 'failed' => 0];

        Transfer::query()
            ->whereNotNull('receipt_path')
            ->select(['id', 'receipt_path'])
            ->lazyById(500)
            ->each(function (Transfer $transfer) use ($cipher, $overwrite, &$counts): void {
                $receiptPath = (string) $transfer->receipt_path;

                if (! $overwrite && $this->receipts->exists($receiptPath)) {
                    $counts['skipped']++;

                    return;
                }

                if (! $this->hasCopy($receiptPath)) {
                    $counts['missing']++;

                    return;
                }

                try {
                    $this->download($cipher, $receiptPath);
                    $counts['restored']++;
                } catch (Throwable $exception) {
                    $counts['failed']++;
                    Log::error('Receipt restore failed.', ['transfer_id' => $transfer->id, 'exception' => $exception::class]);
                }
            });

        return $counts;
    }

    /**
     * يحذف النسخة الاحتياطية لإيصال بعينه (سياسة الاحتفاظ). غياب النسخة ليس خطأ.
     */
    public function deleteCopy(string $receiptPath): void
    {
        $this->disk()->delete($this->backupPath($receiptPath));
    }

    public function hasCopy(string $receiptPath): bool
    {
        return $this->disk()->exists($this->backupPath($receiptPath));
    }

    public function backupPath(string $receiptPath): string
    {
        return $this->prefix().'/'.ltrim($receiptPath, '/').'.enc';
    }

    /**
     * @throws BackupException
     */
    private function upload(BackupCipher $cipher, string $receiptPath, string $target): void
    {
        $source = $this->receipts->disk()->readStream($receiptPath) ?? throw BackupException::io('قراءة الإيصال');
        $encrypted = fopen('php://temp/maxmemory:8388608', 'w+b') ?: throw BackupException::io('إنشاء ملف مؤقت');

        try {
            $cipher->encryptStream($source, $encrypted);
            rewind($encrypted);

            if (! $this->disk()->writeStream($target, $encrypted)) {
                throw BackupException::io('رفع نسخة الإيصال');
            }
        } finally {
            fclose($source);
            fclose($encrypted);
        }
    }

    /**
     * @throws BackupException
     */
    private function download(BackupCipher $cipher, string $receiptPath): void
    {
        $source = $this->disk()->readStream($this->backupPath($receiptPath)) ?? throw BackupException::io('تنزيل نسخة الإيصال');
        $plain = fopen('php://temp/maxmemory:8388608', 'w+b') ?: throw BackupException::io('إنشاء ملف مؤقت');

        try {
            $cipher->decryptStream($source, $plain);
            rewind($plain);

            if (! $this->receipts->disk()->writeStream($receiptPath, $plain)) {
                throw BackupException::io('كتابة الإيصال المسترجع');
            }
        } finally {
            fclose($source);
            fclose($plain);
        }
    }

    private function prefix(): string
    {
        return (string) config('backup.receipts.prefix');
    }

    private function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk((string) config('backup.disk'));
    }
}
