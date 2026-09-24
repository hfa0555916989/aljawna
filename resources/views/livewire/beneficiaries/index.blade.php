<div class="mx-auto max-w-6xl px-4 py-8">
    <h1 class="text-2xl font-bold text-ink">{{ __('site.beneficiaries.title') }}</h1>

    <div role="tablist" aria-label="{{ __('site.beneficiaries.tabs_label') }}" class="mt-6 inline-flex gap-1 rounded-full border border-line bg-surface p-1">
        @foreach ([\App\Livewire\Beneficiaries\Index::TAB_AVAILABLE, \App\Livewire\Beneficiaries\Index::TAB_CLOSED] as $tabKey)
            @php($isActive = $this->activeTab === $tabKey)
            <button
                wire:key="tab-{{ $tabKey }}"
                type="button"
                role="tab"
                id="tab-{{ $tabKey }}"
                aria-selected="{{ $isActive ? 'true' : 'false' }}"
                aria-controls="beneficiaries-panel"
                wire:click="$set('tab', '{{ $tabKey }}')"
                @class([
                    'inline-flex min-h-11 items-center gap-2 rounded-full px-5 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri',
                    'bg-pri text-pri-ink' => $isActive,
                    'text-ink hover:text-pri' => ! $isActive,
                ])
            >
                {{ __('site.beneficiaries.tabs.'.$tabKey) }}
                <span dir="ltr" class="text-xs opacity-80">({{ $this->counts[$tabKey] }})</span>
            </button>
        @endforeach
    </div>

    <div id="beneficiaries-panel" role="tabpanel" aria-labelledby="tab-{{ $this->activeTab }}" class="mt-6">
        @if ($this->beneficiaries->isEmpty())
            <p class="rounded-[14px] border border-line bg-surface p-6 text-center text-muted">
                {{ __('site.beneficiaries.empty.'.$this->activeTab) }}
            </p>
        @else
            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->beneficiaries as $beneficiary)
                    <li wire:key="beneficiary-{{ $beneficiary->id }}" class="flex flex-col gap-4 rounded-[14px] border border-line bg-surface p-4 sm:p-5">
                        <div class="flex items-start justify-between gap-3">
                            <h2 class="min-w-0 break-words text-lg font-bold leading-7 text-ink">
                                <a href="{{ route('beneficiaries.show', $beneficiary) }}" class="hover:text-pri focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">{{ $beneficiary->display_name }}</a>
                            </h2>
                            <x-beneficiary.status :status="$beneficiary->status" />
                        </div>

                        <div>
                            <x-beneficiary.progress :beneficiary="$beneficiary" />
                            <p class="mt-1.5 text-sm text-muted">{{ __('site.beneficiaries.target', ['amount' => \App\Support\Money::format($beneficiary->target_amount)]) }}</p>
                        </div>

                        <dl class="grid gap-3 border-t border-line pt-4">
                            <x-dual-date :label="__('beneficiaries.fields.target_deadline')" :date="$beneficiary->target_deadline" :countdown="$beneficiary->acceptsTransfers()" />
                            <x-dual-date :label="__('beneficiaries.fields.recommended_deadline')" :date="$beneficiary->recommended_deadline" :countdown="$beneficiary->acceptsTransfers()" />
                        </dl>

                        <a
                            href="{{ route('beneficiaries.show', $beneficiary) }}"
                            class="mt-auto inline-flex min-h-11 items-center justify-center rounded-[10px] border border-pri px-4 text-sm font-semibold text-pri hover:bg-brass-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri"
                        >
                            {{ $beneficiary->acceptsTransfers() ? __('site.beneficiaries.details') : __('site.beneficiaries.details_closed') }}
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
