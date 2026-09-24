<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use App\Services\ReceiptStorage;
use App\Services\RepeatedTransferDetector;
use App\Support\AmountInput;
use App\Support\BankReference;
use App\Support\HijriDate;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * رفع حوالة وإيصالها (docs/SPEC.md FR-13..17, §12.3, §12.6).
 *
 * تُحتسب الحوالة فور إنشائها بلا حالة "قيد المراجعة". قواعد المواصفة تُفرض هنا ولو تجاوز
 * المستدعي التحقق في الواجهة: مستفيد معتمد متاح، ومبلغ أكبر من صفر، وتاريخ لا يتجاوز اليوم،
 * والحد اليومي لكل مبادر. يُفحص المستفيد والحد داخل معاملة بأقفال حتى لا يتجاوزهما طلبان متزامنان.
 */
class CreateTransfer
{
    public function __construct(
        private readonly ReceiptStorage $receipts,
        private readonly RepeatedTransferDetector $repeatedTransfers,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(
        User $initiator,
        int $beneficiaryId,
        string $amount,
        string $transferredOn,
        ?string $bankReference,
        UploadedFile $receipt,
    ): Transfer {
        Gate::forUser($initiator)->authorize('create', Transfer::class);

        $normalizedAmount = AmountInput::normalize($amount) ?? throw $this->invalid('amount', 'amount');
        $date = $this->transferDate($transferredOn);
        $reference = BankReference::isValid($bankReference)
            ? BankReference::normalize($bankReference)
            : throw $this->invalid('bank_reference', 'bank_reference');

        $this->ensureWithinDailyLimit($initiator);
        $this->ensureAcceptsTransfers(Beneficiary::query()->find($beneficiaryId));

        $stored = $this->receipts->store($receipt);

        try {
            return DB::transaction(function () use ($initiator, $beneficiaryId, $normalizedAmount, $date, $reference, $stored): Transfer {
                User::query()->whereKey($initiator->id)->lockForUpdate()->first();
                $this->ensureWithinDailyLimit($initiator);
                $this->ensureAcceptsTransfers(Beneficiary::query()->whereKey($beneficiaryId)->sharedLock()->first());

                return Transfer::query()->create([
                    'user_id' => $initiator->id,
                    'beneficiary_id' => $beneficiaryId,
                    'amount' => $normalizedAmount,
                    'transferred_on' => $date,
                    'receipt_path' => $stored->path,
                    'receipt_hash' => $stored->hash,
                    'bank_reference' => $reference,
                    'is_repeated' => $this->repeatedTransfers->isRepeated(
                        $initiator->id, $beneficiaryId, $normalizedAmount, $date, $stored->hash, $reference,
                    ),
                ]);
            });
        } catch (Throwable $exception) {
            $this->receipts->delete($stored->path);

            throw $exception;
        }
    }

    /**
     * عدد ما رفعه المبادر اليوم بتوقيت الرياض.
     */
    public function uploadedToday(User $initiator): int
    {
        return Transfer::query()
            ->where('user_id', $initiator->id)
            ->where('created_at', '>=', now(HijriDate::TIMEZONE)->startOfDay())
            ->count();
    }

    public function reachedDailyLimit(User $initiator): bool
    {
        return $this->uploadedToday($initiator) >= $this->dailyLimit();
    }

    public function dailyLimit(): int
    {
        return max(0, (int) config('security.transfers.daily_limit'));
    }

    /**
     * @throws ValidationException
     */
    private function transferDate(string $value): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, HijriDate::TIMEZONE);

        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw $this->invalid('transferred_on', 'transferred_on');
        }

        if ($date->greaterThan(CarbonImmutable::today(HijriDate::TIMEZONE))) {
            throw $this->invalid('transferred_on', 'future_date');
        }

        return $value;
    }

    /**
     * @throws ValidationException
     */
    private function ensureWithinDailyLimit(User $initiator): void
    {
        if ($this->reachedDailyLimit($initiator)) {
            throw ValidationException::withMessages([
                'form' => __('transfers.validation.daily_limit', ['limit' => $this->dailyLimit()]),
            ]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function ensureAcceptsTransfers(?Beneficiary $beneficiary): void
    {
        if ($beneficiary === null || ! $beneficiary->acceptsTransfers()) {
            throw $this->invalid('beneficiary_id', 'beneficiary');
        }
    }

    private function invalid(string $field, string $reason): ValidationException
    {
        return ValidationException::withMessages([$field => __('transfers.validation.'.$reason)]);
    }
}
