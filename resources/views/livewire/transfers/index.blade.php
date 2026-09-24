<div class="mx-auto max-w-4xl px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-ink">{{ __('transfers.index.title') }}</h1>
        @can('create', \App\Models\Transfer::class)
            <a href="{{ route('transfers.create') }}" class="inline-flex min-h-11 items-center rounded-[10px] bg-pri px-4 text-sm font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">
                {{ __('transfers.index.new') }}
            </a>
        @endcan
    </div>
    <p class="mt-2 leading-7 text-muted">{{ __('transfers.index.lead') }}</p>

    @if (session('status'))
        <p role="status" class="mt-6 rounded-[14px] border border-good bg-surface p-4 font-semibold text-good">{{ session('status') }}</p>
    @endif

    @if ($this->transfers->isEmpty())
        <p class="mt-6 rounded-[14px] border border-line bg-surface p-6 text-center text-muted">{{ __('transfers.index.empty') }}</p>
    @else
        <p class="mt-6 text-sm text-muted">{{ trans_choice('transfers.index.count', $this->transfers->count(), ['count' => $this->transfers->count()]) }}</p>

        <ul class="mt-3 grid gap-3">
            @foreach ($this->transfers as $transfer)
                @php
                    $amount = \App\Support\Money::format($transfer->amount);
                    $date = \App\Support\HijriDate::dual($transfer->transferred_on);
                @endphp
                <li wire:key="transfer-{{ $transfer->id }}" class="grid gap-3 rounded-[14px] border border-line bg-surface p-4 sm:grid-cols-[minmax(0,1fr)_auto_auto] sm:items-center sm:gap-6 sm:p-5">
                    <div class="min-w-0">
                        <p class="text-sm text-muted">{{ __('transfers.fields.beneficiary_id') }}</p>
                        <p class="break-words font-bold leading-7 text-ink">{{ $transfer->beneficiary->display_name }}</p>
                    </div>

                    <dl class="flex flex-wrap gap-x-6 gap-y-3">
                        <div>
                            <dt class="text-sm text-muted">{{ __('transfers.fields.amount') }}</dt>
                            <dd class="font-bold text-ink">{{ $amount }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm text-muted">{{ __('transfers.fields.transferred_on') }}</dt>
                            <dd>
                                <span class="block font-semibold text-ink">{{ $date['hijri'] ?? '' }}</span>
                                <span class="block text-sm text-muted">{{ $date['gregorian'] ?? '' }}</span>
                            </dd>
                        </div>
                    </dl>

                    <a
                        href="{{ $this->receiptUrl($transfer) }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        aria-label="{{ __('transfers.index.view_receipt_aria', ['amount' => $amount, 'name' => $transfer->beneficiary->display_name]) }}"
                        class="inline-flex min-h-11 items-center justify-center rounded-[10px] border border-pri px-4 text-sm font-semibold text-pri hover:bg-brass-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri"
                    >
                        {{ __('transfers.index.view_receipt') }}
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
