<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\StatsService;
use App\TransferReviewState;
use Database\Factories\TransferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * حوالة مبادر إلى مستفيد مع إيصالها (docs/SPEC.md §9 transfers, FR-13..17).
 *
 * تدخل الإحصائيات فور إنشائها، وتُنشأ عبر App\Actions\Transfers\CreateTransfer.
 * حقول المراجعة (review_state وassigned_* وfinal_*) لا تُعبَّأ جماعيًا.
 *
 * @property int $user_id
 * @property int $beneficiary_id
 * @property numeric-string $amount
 * @property Carbon $transferred_on
 * @property string $receipt_path
 * @property string $receipt_hash
 * @property string|null $bank_reference
 * @property bool $is_repeated
 * @property TransferReviewState $review_state
 * @property Carbon|null $assigned_at
 * @property Carbon|null $final_reviewed_at
 */
#[Fillable([
    'user_id', 'beneficiary_id', 'amount', 'transferred_on',
    'receipt_path', 'receipt_hash', 'bank_reference', 'is_repeated',
])]
class Transfer extends Model
{
    /** @use HasFactory<TransferFactory> */
    use HasFactory;

    /**
     * المبادر صاحب الحوالة.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Beneficiary, $this>
     */
    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->user_id === $user->id;
    }

    /**
     * يُبطل كاش مؤشرات المستفيد ومجموع المبادرة عند إضافة حوالة أو تعديلها (docs/SPEC.md §7).
     */
    protected static function booted(): void
    {
        static::saved(function (Transfer $transfer): void {
            StatsService::forget($transfer->beneficiary_id);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transferred_on' => 'date',
            'is_repeated' => 'boolean',
            'review_state' => TransferReviewState::class,
            'assigned_at' => 'datetime',
            'final_reviewed_at' => 'datetime',
        ];
    }
}
