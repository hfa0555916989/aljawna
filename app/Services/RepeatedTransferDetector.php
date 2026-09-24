<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transfer;
use Illuminate\Database\Eloquent\Builder;

/**
 * كشف الحوالة المتكررة (docs/SPEC.md FR-17، والتعريف المعتمد مؤقتًا في §14 بند 4).
 *
 * الحوالة الجديدة متكررة إن وُجدت حوالة سابقة لها:
 * - نفس بصمة الإيصال (أيًّا كان المبادر)، أو
 * - نفس رقم العملية المصرفية (أيًّا كان المبادر، ولا تُطابق القيم الفارغة)، أو
 * - نفس المبادر والمستفيد والمبلغ والتاريخ.
 *
 * العلامة للتنبيه والمطابقة فقط، ولا تمنع الحوالة ولا تُخرجها من الإحصائيات.
 */
class RepeatedTransferDetector
{
    /**
     * @param  numeric-string  $amount
     */
    public function isRepeated(
        int $userId,
        int $beneficiaryId,
        string $amount,
        string $transferredOn,
        string $receiptHash,
        ?string $bankReference,
    ): bool {
        return Transfer::query()
            ->where(function (Builder $query) use ($userId, $beneficiaryId, $amount, $transferredOn, $receiptHash, $bankReference): void {
                $query->where('receipt_hash', $receiptHash)
                    ->when($bankReference !== null, fn (Builder $query) => $query->orWhere('bank_reference', $bankReference))
                    ->orWhere(fn (Builder $query) => $query
                        ->where('user_id', $userId)
                        ->where('beneficiary_id', $beneficiaryId)
                        ->where('amount', $amount)
                        ->where('transferred_on', $transferredOn));
            })
            ->exists();
    }
}
