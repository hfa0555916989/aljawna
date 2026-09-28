<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemHeartbeat;
use App\Services\ReceiptBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * نسخ الإيصالات الجديدة فقط، مشفّرة، إلى وجهة النسخ خارج Laravel Cloud (T21).
 * يشغّله المجدول كل ساعة، ويسجّل حالته في "صحة النظام"؛ الفشل يطلق تنبيه البريد
 * من monitor:check.
 */
class BackupReceiptsCommand extends Command
{
    protected $signature = 'backup:receipts';

    protected $description = 'نسخ الإيصالات الجديدة مشفّرة إلى وجهة النسخ الاحتياطي';

    public function handle(ReceiptBackup $backup): int
    {
        if (! config('monitoring.backup.enabled')) {
            $this->warn('نسخ الإيصالات غير مُعدّ (RECEIPTS_BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        try {
            $result = $backup->run();
        } catch (Throwable $exception) {
            SystemHeartbeat::recordReceiptsBackup(false);
            Log::error('Receipts backup failed.', ['exception' => $exception::class]);
            $this->error('فشل نسخ الإيصالات: '.$exception->getMessage());

            return self::FAILURE;
        }

        SystemHeartbeat::recordReceiptsBackup($result['failed'] === 0);
        $this->info("نُسخ {$result['uploaded']} إيصال جديد، وفشل {$result['failed']}.");

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
