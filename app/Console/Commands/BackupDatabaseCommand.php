<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SystemHeartbeat;
use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * نسخة قاعدة البيانات اليومية المشفّرة إلى وجهة النسخ خارج Laravel Cloud (T21)، ثم
 * حذف ما خرج عن سياسة الاحتفاظ (14 يومًا / 8 أسابيع / 6 أشهر) بعد نجاحها فقط.
 * تُسجَّل الحالة في "صحة النظام"، والفشل يطلق تنبيه البريد من monitor:check.
 */
class BackupDatabaseCommand extends Command
{
    protected $signature = 'backup:database';

    protected $description = 'نسخة مشفّرة من قاعدة البيانات إلى وجهة النسخ الاحتياطي مع تطبيق سياسة الاحتفاظ';

    public function handle(DatabaseBackup $backup): int
    {
        if (! config('monitoring.database_backup.enabled')) {
            $this->warn('نسخ قاعدة البيانات غير مُعدّ (DATABASE_BACKUP_ENABLED=false).');

            return self::SUCCESS;
        }

        try {
            $path = $backup->create();
        } catch (Throwable $exception) {
            SystemHeartbeat::recordDatabaseBackup(false);
            Log::error('Database backup failed.', ['exception' => $exception::class]);
            $this->error('فشل نسخ قاعدة البيانات: '.$exception->getMessage());

            return self::FAILURE;
        }

        SystemHeartbeat::recordDatabaseBackup(true);
        $this->info('أُنشئت النسخة: '.$path);

        try {
            $deleted = $backup->prune();
            $this->line('حُذفت '.count($deleted).' نسخة قديمة وفق سياسة الاحتفاظ.');
        } catch (Throwable $exception) {
            Log::warning('Database backup pruning failed.', ['exception' => $exception::class]);
            $this->warn('تعذّر حذف النسخ القديمة: '.$exception->getMessage());
        }

        return self::SUCCESS;
    }
}
