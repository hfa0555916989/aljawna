<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

/**
 * يعرض نسخ قاعدة البيانات الموجودة في وجهة النسخ (للاختيار منها عند الاسترجاع).
 */
class ListBackupsCommand extends Command
{
    protected $signature = 'backup:list';

    protected $description = 'عرض نسخ قاعدة البيانات الموجودة في وجهة النسخ الاحتياطي';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            $backups = $backup->list();
        } catch (Throwable $exception) {
            $this->error('تعذّرت قراءة وجهة النسخ: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($backups === []) {
            $this->warn('لا توجد نسخ.');

            return self::SUCCESS;
        }

        $this->table(
            ['النسخة', 'وقت الإنشاء (الرياض)'],
            collect($backups)->map(fn ($createdAt, $path): array => [
                (string) $path,
                $createdAt->timezone('Asia/Riyadh')->format('Y-m-d H:i'),
            ])->values()->all(),
        );

        return self::SUCCESS;
    }
}
