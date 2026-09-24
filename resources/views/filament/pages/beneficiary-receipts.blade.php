<x-filament-panels::page>
    @forelse ($this->beneficiaries as $beneficiary)
        <x-filament::section
            wire:key="beneficiary-{{ $beneficiary->id }}"
            id="beneficiary-{{ $beneficiary->id }}"
            data-beneficiary="{{ $beneficiary->id }}"
        >
            <x-slot name="heading">
                <span class="break-words">{{ $beneficiary->display_name }}</span>
            </x-slot>

            <x-slot name="description">
                {{ trans_choice('transfers.index.count', $beneficiary->transfers_count, ['count' => $beneficiary->transfers_count]) }}
                · {{ __('transfers.admin.collected', ['amount' => \App\Support\Money::format($beneficiary->collectedAmount())]) }}
            </x-slot>

            <x-slot name="afterHeader">
                <div class="flex flex-wrap gap-2">
                    <x-filament::badge :color="$beneficiary->status === \App\BeneficiaryStatus::Active ? 'success' : 'gray'">
                        {{ __('beneficiaries.status.'.$beneficiary->status->value) }}
                    </x-filament::badge>
                    <x-filament::badge :color="\App\Filament\Resources\Beneficiaries\BeneficiaryResource::approvalColor($beneficiary)">
                        {{ \App\Filament\Resources\Beneficiaries\BeneficiaryResource::approvalLabel($beneficiary) }}
                    </x-filament::badge>
                </div>
            </x-slot>

            @if ($beneficiary->transfers_count === 0)
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('transfers.admin.no_transfers') }}</p>
            @else
                @php
                    $transfers = $this->transfersOf($beneficiary);
                @endphp
                <ul role="list" class="divide-y divide-gray-200 dark:divide-white/10">
                    @foreach ($transfers as $transfer)
                        @php
                            $amount = \App\Support\Money::format($transfer->amount);
                            $date = \App\Support\HijriDate::dual($transfer->transferred_on);
                        @endphp
                        <li
                            wire:key="transfer-{{ $transfer->id }}"
                            data-transfer="{{ $transfer->id }}"
                            class="grid gap-3 py-3 first:pt-0 last:pb-0 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center sm:gap-6"
                        >
                            <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm lg:grid-cols-4">
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('transfers.fields.amount') }}</dt>
                                    <dd class="font-semibold text-gray-950 dark:text-white">{{ $amount }}</dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('transfers.fields.transferred_on') }}</dt>
                                    <dd class="text-gray-950 dark:text-white">
                                        <span class="block font-semibold">{{ $date['hijri'] ?? '' }}</span>
                                        <span class="block text-gray-500 dark:text-gray-400">{{ $date['gregorian'] ?? '' }}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('transfers.admin.initiator') }}</dt>
                                    <dd class="text-gray-950 dark:text-white"><span class="break-words">{{ $transfer->user->full_name }}</span></dd>
                                </div>
                                <div>
                                    <dt class="text-gray-500 dark:text-gray-400">{{ __('transfers.fields.bank_reference') }}</dt>
                                    <dd class="text-gray-950 dark:text-white">
                                        @if ($transfer->bank_reference !== null)
                                            <span dir="ltr">{{ $transfer->bank_reference }}</span>
                                        @else
                                            <span class="text-gray-500 dark:text-gray-400">—</span>
                                        @endif
                                    </dd>
                                </div>
                            </dl>

                            <div class="flex flex-wrap items-center gap-2">
                                @if ($transfer->is_repeated)
                                    <x-filament::badge color="warning">{{ __('transfers.admin.repeated') }}</x-filament::badge>
                                @endif
                                <x-filament::button
                                    tag="a"
                                    :href="$this->receiptUrl($transfer)"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    outlined
                                    size="sm"
                                    :aria-label="__('transfers.index.view_receipt_aria', ['amount' => $amount, 'name' => $beneficiary->display_name])"
                                >
                                    {{ __('transfers.index.view_receipt') }}
                                </x-filament::button>
                            </div>
                        </li>
                    @endforeach
                </ul>

                @if ($transfers->hasPages())
                    <div class="mt-4" data-transfers-pagination="{{ $beneficiary->id }}">
                        <x-filament::pagination :paginator="$transfers" />
                    </div>
                @endif
            @endif
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('transfers.admin.empty') }}</p>
        </x-filament::section>
    @endforelse

    <x-filament::pagination :paginator="$this->beneficiaries" />
</x-filament-panels::page>
