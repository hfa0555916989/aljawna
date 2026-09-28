<?php

declare(strict_types=1);

namespace App\Services;

use App\BeneficiaryStatus;
use App\Models\Beneficiary;
use App\Models\Transfer;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * سياسة الاحتفاظ بصور الإيصالات (T21، docs/DECISIONS.md): بعد إقفال المبادرة بمدة
 * security.receipts.retention_months (6 أشهر) تُحذف صور إيصالاتها من التخزين ومن
 * النسخ الاحتياطية، وتبقى الحوالة نفسها دائمًا (المبلغ والتاريخ والمبادر وبصمة الإيصال)
 * فلا تتغير الإحصائيات ولا كشف التكرار.
 *
 * المبادرة المفتوحة لا تُمسّ أبدًا: الشرط حالة "مغلقة" وتاريخ إقفال مسجّل قبل المدة،
 * ويُعاد فحصه تحت قفل الصف لحظة الحذف (قد تُعاد المبادرة للفتح في الأثناء).
 * الترتيب لكل إيصال: النسخة الاحتياطية ثم الأصل ثم تفريغ المسار؛ فشل أي خطوة يُبقي
 * المسار فيُعاد المحاولة في التشغيل التالي، ولا تبقى نسخة يتيمة لا يدلّ عليها شيء.
 */
class ReceiptRetention
{
    public const string AUDIT_PURGED = 'receipts.purged';

    public function __construct(
        private ReceiptStorage $receipts,
        private ReceiptBackup $backups,
    ) {}

    public function cutoff(): CarbonInterface
    {
        return now()->subMonths((int) config('security.receipts.retention_months'));
    }

    /**
     * المبادرات التي انتهت مدة الاحتفاظ بإيصالاتها وما زال لها صور محفوظة.
     *
     * @return Collection<int, Beneficiary>
     */
    public function expiredBeneficiaries(): Collection
    {
        $withReceipt = fn (Builder $query): Builder => $query->whereNotNull('receipt_path');

        return Beneficiary::query()
            ->where('status', BeneficiaryStatus::Closed)
            ->whereNotNull('closed_at')
            ->where('closed_at', '<=', $this->cutoff())
            ->whereHas('transfers', $withReceipt)
            ->withCount(['transfers as stored_receipts_count' => $withReceipt])
            ->orderBy('id')
            ->get();
    }

    public function isExpired(?Beneficiary $beneficiary): bool
    {
        return $beneficiary !== null
            && $beneficiary->status === BeneficiaryStatus::Closed
            && $beneficiary->closed_at !== null
            && $beneficiary->closed_at->lte($this->cutoff());
    }

    /**
     * يحذف صور إيصالات مبادرة واحدة، ويسجّل العملية في audit_logs.
     *
     * @return array{purged: int, failed: int}
     */
    public function purge(Beneficiary $beneficiary): array
    {
        return DB::transaction(function () use ($beneficiary): array {
            $locked = Beneficiary::query()->lockForUpdate()->find($beneficiary->getKey());

            if (! $locked instanceof Beneficiary || ! $this->isExpired($locked)) {
                return ['purged' => 0, 'failed' => 0];
            }

            $purged = 0;
            $failed = 0;

            $locked->transfers()
                ->whereNotNull('receipt_path')
                ->lazyById(200)
                ->each(function (Transfer $transfer) use (&$purged, &$failed): void {
                    $receiptPath = (string) $transfer->receipt_path;

                    try {
                        $this->backups->deleteCopy($receiptPath);
                        $this->receipts->delete($receiptPath);
                        $transfer->forceFill(['receipt_path' => null, 'receipt_purged_at' => now()])->save();
                        $purged++;
                    } catch (Throwable $exception) {
                        $failed++;
                        Log::error('Receipt purge failed.', ['transfer_id' => $transfer->id, 'exception' => $exception::class]);
                    }
                });

            if ($purged > 0 || $failed > 0) {
                Audit::record(self::AUDIT_PURGED, $locked, [
                    'purged' => $purged,
                    'failed' => $failed,
                    'closed_at' => $locked->closed_at?->toIso8601String(),
                    'retention_months' => (int) config('security.receipts.retention_months'),
                    'issued_via' => 'cli',
                ]);
            }

            return ['purged' => $purged, 'failed' => $failed];
        });
    }
}
