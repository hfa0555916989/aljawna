<?php

declare(strict_types=1);

namespace App\Livewire\Transfers;

use App\Models\Transfer;
use App\Models\User;
use App\Services\ReceiptStorage;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * صفحة "حوالاتي" /my-transfers (docs/SPEC.md FR-16): كل حوالات المبادر بلا استثناء،
 * مع المستفيد والمبلغ والتاريخ ورابط موقّع مؤقت لإيصاله.
 */
class Index extends Component
{
    /**
     * @return Collection<int, Transfer>
     */
    #[Computed]
    public function transfers(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->transfers()
            ->with('beneficiary:id,display_name')
            ->orderByDesc('transferred_on')
            ->orderByDesc('id')
            ->get(['id', 'user_id', 'beneficiary_id', 'amount', 'transferred_on', 'receipt_path']);
    }

    public function receiptUrl(Transfer $transfer): string
    {
        return app(ReceiptStorage::class)->temporaryUrl($transfer);
    }

    public function render(): View
    {
        return view('livewire.transfers.index')
            ->title(__('transfers.index.title').' — '.config('app.name'));
    }
}
