<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReceiptRetention;
use Illuminate\Console\Command;

/**
 * يحذف صور إيصالات المبادرات المقفلة منذ أكثر من مدة الاحتفاظ (6 أشهر) من التخزين ومن
 * النسخ الاحتياطية، وتبقى بيانات الحوالات. يشغّله المجدول يوميًا، ويُسجَّل في audit_logs.
 *
 *     php artisan receipts:purge-expired --dry-run   عرض ما سيُحذف دون حذف
 */
class PurgeExpiredReceiptsCommand extends Command
{
    protected $signature = 'receipts:purge-expired {--dry-run : اعرض ما سيُحذف دون حذف شيء}';

    protected $description = 'حذف صور إيصالات المبادرات المقفلة بعد انتهاء مدة الاحتفاظ';

    public function handle(ReceiptRetention $retention): int
    {
        $beneficiaries = $retention->expiredBeneficiaries();
        $dryRun = (bool) $this->option('dry-run');

        if ($beneficiaries->isEmpty()) {
            $this->info('لا إيصالات انتهت مدة الاحتفاظ بها (قبل '.$retention->cutoff()->toDateString().').');

            return self::SUCCESS;
        }

        $failed = 0;
        $rows = [];

        foreach ($beneficiaries as $beneficiary) {
            $count = (int) $beneficiary->getAttribute('stored_receipts_count');

            if ($dryRun) {
                $rows[] = [$beneficiary->id, $beneficiary->closed_at?->toDateString(), $count, '—'];

                continue;
            }

            $result = $retention->purge($beneficiary);
            $failed += $result['failed'];
            $rows[] = [$beneficiary->id, $beneficiary->closed_at?->toDateString(), $count, $result['purged']];
        }

        $this->table(['المبادرة', 'تاريخ الإقفال', 'صور محفوظة', 'حُذفت'], $rows);

        if ($dryRun) {
            $this->warn('تجربة فقط (--dry-run): لم يُحذف شيء.');

            return self::SUCCESS;
        }

        if ($failed > 0) {
            $this->error("فشل حذف {$failed} إيصالًا، ويُعاد المحاولة في التشغيل التالي.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
