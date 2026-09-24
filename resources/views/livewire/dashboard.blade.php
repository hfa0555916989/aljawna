<div class="mx-auto max-w-6xl px-4 py-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <h1 class="text-2xl font-bold text-ink">{{ __('dashboard.title') }}</h1>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex min-h-11 items-center rounded-[10px] border border-line px-4 text-sm font-semibold text-ink hover:bg-surface">
                {{ __('dashboard.logout') }}
            </button>
        </form>
    </div>
    <p class="mt-2 break-words text-muted">{{ __('dashboard.greeting', ['name' => auth()->user()?->full_name]) }}</p>

    @can('create', \App\Models\Transfer::class)
        <div class="mt-6 flex flex-wrap gap-3">
            <a
                href="{{ route('transfers.create') }}"
                class="inline-flex min-h-11 items-center rounded-[10px] bg-pri px-5 text-sm font-semibold text-white hover:opacity-90"
            >
                {{ __('transfers.dashboard.upload') }}
            </a>
            <a
                href="{{ route('transfers.index') }}"
                class="inline-flex min-h-11 items-center rounded-[10px] border border-line px-5 text-sm font-semibold text-ink hover:bg-surface"
            >
                {{ __('transfers.dashboard.mine') }}
            </a>
        </div>
    @endcan

    <section aria-labelledby="cards-heading" class="mt-8">
        <h2 id="cards-heading" class="text-xl font-bold text-ink">{{ __('dashboard.cards.heading') }}</h2>
        <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3 sm:gap-4 lg:grid-cols-4">
            <div class="rounded-[14px] border border-line bg-surface p-4">
                <span class="block text-3xl font-bold text-brass" dir="ltr" data-stat="available">{{ $this->beneficiaryCounts['available'] }}</span>
                <span class="mt-1 block text-sm font-semibold text-muted">{{ __('dashboard.cards.available') }}</span>
            </div>
            <div class="rounded-[14px] border border-line bg-surface p-4">
                <span class="block text-3xl font-bold text-brass" dir="ltr" data-stat="closed">{{ $this->beneficiaryCounts['closed'] }}</span>
                <span class="mt-1 block text-sm font-semibold text-muted">{{ __('dashboard.cards.closed') }}</span>
            </div>
            <div class="rounded-[14px] border border-line bg-surface p-4">
                <span class="block text-3xl font-bold text-brass" dir="ltr" data-stat="initiators">{{ $this->stats['initiators_count'] }}</span>
                <span class="mt-1 block text-sm font-semibold text-muted">{{ __('dashboard.cards.initiators') }}</span>
            </div>
            <div class="rounded-[14px] border border-line bg-surface p-4">
                <span class="block text-2xl font-bold text-brass sm:text-3xl" dir="ltr" data-stat="total">{{ \App\Support\Money::format($this->stats['total']) }}</span>
                <span class="mt-1 block text-sm font-semibold text-muted">{{ __('dashboard.cards.total') }}</span>
            </div>
            <div class="rounded-[14px] border border-line bg-surface p-4">
                <span class="block text-3xl font-bold text-brass" dir="ltr" data-stat="receipts">{{ $this->stats['receipts_count'] }}</span>
                <span class="mt-1 block text-sm font-semibold text-muted">{{ __('dashboard.cards.receipts') }}</span>
            </div>
            <div class="rounded-[14px] border border-line bg-surface p-4">
                <span class="block text-2xl font-bold text-brass sm:text-3xl" dir="ltr" data-stat="average">{{ \App\Support\Money::format($this->stats['average']) }}</span>
                <span class="mt-1 block text-sm font-semibold text-muted">{{ __('dashboard.cards.average') }}</span>
            </div>
            <div class="rounded-[14px] border border-line bg-surface p-4">
                <span class="block text-3xl font-bold text-brass" dir="ltr" data-stat="remaining">{{ $this->stats['remaining_percentage'] }}%</span>
                <span class="mt-1 block text-sm font-semibold text-muted">{{ __('dashboard.cards.remaining') }}</span>
            </div>
        </div>
    </section>

    <section aria-labelledby="dates-heading" class="mt-8 rounded-[14px] border border-line bg-surface p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="dates-heading" class="text-lg font-bold text-ink">{{ __('dashboard.dates.heading') }}</h2>
            @if ($this->availableBeneficiaries->isNotEmpty())
                <label class="flex items-center gap-2 text-sm">
                    <span class="sr-only">{{ __('dashboard.dates.select_label') }}</span>
                    <select wire:model.live="selectedBeneficiaryId" class="min-h-11 rounded-[10px] border border-line bg-surface px-3 text-sm text-ink">
                        @foreach ($this->availableBeneficiaries as $option)
                            <option value="{{ $option->id }}">{{ $option->display_name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
        </div>

        @if ($this->selectedBeneficiary)
            <dl class="mt-4 grid gap-4 sm:grid-cols-3">
                <x-dual-date :label="__('beneficiaries.fields.target_deadline')" :date="$this->selectedBeneficiary->target_deadline" :countdown="true" />
                <x-dual-date :label="__('beneficiaries.fields.recommended_deadline')" :date="$this->selectedBeneficiary->recommended_deadline" :countdown="true" />
                <x-dual-date :label="__('beneficiaries.fields.wedding_date')" :date="$this->selectedBeneficiary->wedding_date" />
            </dl>
        @else
            <p class="mt-3 text-muted">{{ __('dashboard.dates.empty') }}</p>
        @endif
    </section>

    <section aria-labelledby="latest-heading" class="mt-8 rounded-[14px] border border-line bg-surface p-5">
        <h2 id="latest-heading" class="text-lg font-bold text-ink">{{ __('dashboard.latest.heading') }}</h2>
        @if ($this->recentTransfers->isEmpty())
            <p class="mt-3 text-muted">{{ __('dashboard.latest.empty') }}</p>
        @else
            <ul class="mt-4 flex flex-col gap-3">
                @foreach ($this->recentTransfers as $transfer)
                    <li wire:key="recent-{{ $transfer->id }}" class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line pb-3 text-sm last:border-b-0 last:pb-0">
                        <span class="text-ink">
                            <span class="font-semibold">{{ $transfer->user->firstName() }}</span>
                            {{ __('dashboard.latest.transferred') }}
                            <span class="font-semibold" dir="ltr">{{ \App\Support\Money::format($transfer->amount) }}</span>
                            {{ __('dashboard.latest.supporting') }}
                            <span class="font-semibold">{{ $transfer->beneficiary->display_name }}</span>
                        </span>
                        <span class="shrink-0 text-xs text-muted">{{ $transfer->created_at->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
