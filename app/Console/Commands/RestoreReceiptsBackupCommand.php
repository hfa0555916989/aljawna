<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReceiptBackup;
use Illuminate\Console\Command;
use Throwable;

/**
 * يعيد صور الإيصالات المفقودة من التخزين من نسخها المشفّرة (docs/RUNBOOK.md).
 * الافتراضي لا يكتب فوق إيصال موجود؛ --overwrite يعيدها كلها.
 */
class RestoreReceiptsBackupCommand extends Command
{
    protected $signature = 'backup:restore-receipts
        {--overwrite : اكتب فوق الإيصالات الموجودة أيضًا}
        {--force : دون طلب تأكيد}';

    protected $description = 'استرجاع صور الإيصالات من نسخها الاحتياطية المشفّرة';

    public function handle(ReceiptBackup $backup): int
    {
        if (! $this->option('force') && ! $this->confirm('استرجاع الإيصالات المفقودة من النسخ الاحتياطية؟')) {
            $this->warn('أُلغي الاسترجاع.');

            return self::FAILURE;
        }

        try {
            $result = $backup->restore((bool) $this->option('overwrite'));
        } catch (Throwable $exception) {
            $this->error('فشل: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info("استُرجع {$result['restored']} إيصالًا، وتُرك {$result['skipped']} موجودًا.");

        if ($result['missing'] > 0 || $result['failed'] > 0) {
            $this->warn("بلا نسخة احتياطية: {$result['missing']}، وفشل: {$result['failed']}.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
