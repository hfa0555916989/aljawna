<?php

declare(strict_types=1);

namespace App\Models;

use App\BeneficiaryStatus;
use App\Support\BankAccountNumber;
use App\Support\SaudiIban;
use Database\Factories\BeneficiaryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * المستفيد ومبادرته (docs/SPEC.md §9 beneficiaries, FR-28..33).
 *
 * كل استعلام عام يمرّ عبر public() أو available()، فلا يظهر غير المعتمد للعامة.
 *
 * @property BeneficiaryStatus $status
 * @property numeric-string $target_amount
 * @property Carbon $target_deadline
 * @property Carbon $recommended_deadline
 * @property Carbon $wedding_date
 * @property Carbon|null $approved_at
 */
#[Fillable([
    'display_name', 'account_holder', 'bank_name', 'account_number', 'iban',
    'target_amount', 'target_deadline', 'recommended_deadline', 'wedding_date',
])]
class Beneficiary extends Model
{
    /** @use HasFactory<BeneficiaryFactory> */
    use HasFactory;

    /**
     * الحقول البنكية: تعديل أيٍّ منها يتطلب تأكيدًا صريحًا ويُسجَّل (docs/SPEC.md §12.10).
     */
    public const BANK_FIELDS = ['account_holder', 'bank_name', 'account_number', 'iban'];

    /**
     * المعتمدة فقط، متاحة كانت أو مغلقة. أساس كل عرض عام.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function public(Builder $query): void
    {
        $query->whereNotNull('approved_at');
    }

    /**
     * المعتمدة المتاحة: تُعرض حساباتها وتستقبل الحوالات.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->whereNotNull('approved_at')->where('status', BeneficiaryStatus::Active);
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function acceptsTransfers(): bool
    {
        return $this->isApproved() && $this->status === BeneficiaryStatus::Active;
    }

    /**
     * @return Attribute<string, string>
     */
    protected function iban(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => SaudiIban::normalize($value));
    }

    /**
     * @return Attribute<string, string>
     */
    protected function accountNumber(): Attribute
    {
        return Attribute::make(set: fn (?string $value): string => BankAccountNumber::normalize($value));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'target_deadline' => 'date',
            'recommended_deadline' => 'date',
            'wedding_date' => 'date',
            'status' => BeneficiaryStatus::class,
            'approved_at' => 'datetime',
        ];
    }
}
