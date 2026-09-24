<?php

declare(strict_types=1);

namespace App\Livewire\Transfers;

use App\Actions\Transfers\CreateTransfer;
use App\BeneficiaryStatus;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use App\Rules\BankReference;
use App\Rules\ReceiptFile;
use App\Rules\TransferAmount;
use App\Services\ReceiptStorage;
use App\Support\HijriDate;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * صفحة رفع حوالة /transfers (docs/SPEC.md FR-13, FR-14, FR-17, §12.6).
 *
 * يختار المبادر مستفيدًا من المتاحة فقط، والتحقق النهائي وقواعد الحفظ في CreateTransfer.
 */
class Create extends Component
{
    use WithFileUploads;

    public string $beneficiary_id = '';

    public string $amount = '';

    public string $transferred_on = '';

    public string $bank_reference = '';

    public ?TemporaryUploadedFile $receipt = null;

    public function mount(): void
    {
        $this->authorize('create', Transfer::class);

        $this->transferred_on = $this->today();

        $requested = request()->integer('beneficiary');

        if ($requested > 0 && $this->beneficiaries()->contains('id', $requested)) {
            $this->beneficiary_id = (string) $requested;
        }
    }

    /**
     * المستفيدون المعتمدون المتاحون فقط، بأسمائهم دون أي عمود بنكي.
     *
     * @return Collection<int, Beneficiary>
     */
    #[Computed]
    public function beneficiaries(): Collection
    {
        return Beneficiary::available()->orderBy('target_deadline')->orderBy('id')->get(['id', 'display_name']);
    }

    #[Computed]
    public function transferredOnHijri(): ?string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $this->transferred_on, HijriDate::TIMEZONE);

        return $date instanceof CarbonImmutable && $date->format('Y-m-d') === $this->transferred_on
            ? HijriDate::format($date)
            : null;
    }

    #[Computed]
    public function dailyLimitReached(): bool
    {
        return app(CreateTransfer::class)->reachedDailyLimit($this->initiator());
    }

    public function updatedReceipt(): void
    {
        $this->validateOnly('receipt');
    }

    public function save(CreateTransfer $createTransfer): void
    {
        $this->authorize('create', Transfer::class);

        $this->validate();

        /** @var TemporaryUploadedFile $receipt */
        $receipt = $this->receipt;

        $createTransfer->handle(
            $this->initiator(),
            (int) $this->beneficiary_id,
            $this->amount,
            $this->transferred_on,
            $this->bank_reference,
            $receipt,
        );

        $receipt->delete();

        session()->flash('status', __('transfers.index.created'));

        $this->redirectRoute('transfers.index');
    }

    public function render(): View
    {
        return view('livewire.transfers.create', [
            'today' => $this->today(),
        ])->title(__('transfers.create.title').' — '.config('app.name'));
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'beneficiary_id' => [
                'required',
                'integer',
                Rule::exists('beneficiaries', 'id')->whereNotNull('approved_at')->where('status', BeneficiaryStatus::Active->value),
            ],
            'amount' => ['required', 'string', 'max:32', new TransferAmount],
            'transferred_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$this->today()],
            'bank_reference' => ['nullable', 'string', 'max:100', new BankReference],
            'receipt' => ['required', 'file', 'max:'.ReceiptStorage::MAX_KILOBYTES, new ReceiptFile],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'beneficiary_id.required' => __('transfers.validation.beneficiary'),
            'beneficiary_id.integer' => __('transfers.validation.beneficiary'),
            'beneficiary_id.exists' => __('transfers.validation.beneficiary'),
            'transferred_on.required' => __('transfers.validation.transferred_on'),
            'transferred_on.date_format' => __('transfers.validation.transferred_on'),
            'transferred_on.before_or_equal' => __('transfers.validation.future_date'),
            'receipt.required' => __('transfers.validation.receipt_required'),
            'receipt.file' => __('transfers.validation.receipt_type'),
            'receipt.uploaded' => __('transfers.validation.receipt_size'),
            'receipt.max' => __('transfers.validation.receipt_size'),
        ];
    }

    private function initiator(): User
    {
        /** @var User */
        return Auth::user();
    }

    private function today(): string
    {
        return CarbonImmutable::today(HijriDate::TIMEZONE)->toDateString();
    }
}
