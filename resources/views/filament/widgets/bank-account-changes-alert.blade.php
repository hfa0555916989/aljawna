@php
    use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
    use App\Models\Beneficiary;
@endphp

<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
        <x-slot name="heading">
            <span role="alert">{{ __('beneficiaries.alert.heading') }}</span>
        </x-slot>

        <x-slot name="description">
            {{ __('beneficiaries.alert.description', ['days' => $days]) }}
        </x-slot>

        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($changes as $change)
                @php($beneficiary = $change->subject)
                <li class="py-3 first:pt-0 last:pb-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-medium text-gray-950 dark:text-white">
                            {{ __('beneficiaries.alert.line', [
                                'actor' => $change->actor?->full_name ?? __('beneficiaries.alert.unknown_actor'),
                                'beneficiary' => $beneficiary instanceof Beneficiary ? $beneficiary->display_name : '#'.$change->subject_id,
                                'time' => $change->created_at->diffForHumans(),
                            ]) }}
                        </p>

                        @if ($beneficiary instanceof Beneficiary)
                            <x-filament::link :href="BeneficiaryResource::getUrl('edit', ['record' => $beneficiary])" size="sm">
                                {{ __('beneficiaries.alert.open') }}
                            </x-filament::link>
                        @endif
                    </div>

                    <ul class="mt-1 space-y-1 text-sm text-gray-600 dark:text-gray-300">
                        @foreach ($change->meta['changes'] ?? [] as $field => $values)
                            @php($isNumber = in_array($field, ['account_number', 'iban'], true))
                            <li>
                                {{ __('beneficiaries.fields.'.$field) }}:
                                <span dir="{{ $isNumber ? 'ltr' : 'auto' }}" @class(['font-mono' => $isNumber])>{{ $values['old'] ?? '' }}</span>
                                ←
                                <span dir="{{ $isNumber ? 'ltr' : 'auto' }}" @class(['font-mono' => $isNumber])>{{ $values['new'] ?? '' }}</span>
                            </li>
                        @endforeach
                    </ul>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
