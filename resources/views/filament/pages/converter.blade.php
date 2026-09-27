<div class="flex flex-col gap-6">
    <nav class="flex flex-wrap gap-2" aria-label="{{ __('converter.navigation') }}">
        @foreach (\App\Filament\Pages\Converter::DIRECTIONS as $directionKey)
            <button
                type="button"
                wire:click="selectDirection('{{ $directionKey }}')"
                aria-pressed="{{ $direction === $directionKey ? 'true' : 'false' }}"
                class="min-h-11 rounded-lg px-3 {{ $direction === $directionKey ? 'bg-primary-600 text-white' : 'border' }}"
            >
                {{ __('converter.directions.'.$directionKey) }}
            </button>
        @endforeach
    </nav>

    <form class="grid gap-3 sm:grid-cols-3" wire:submit.prevent>
        @if ($direction === \App\Filament\Pages\Converter::DIRECTION_GREGORIAN_TO_HIJRI)
            <label class="block text-sm sm:col-span-1">
                {{ __('converter.gregorian_date') }}
                <input type="date" wire:model.live="gregorianDate" dir="ltr" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start" data-converter-input="gregorian">
            </label>
        @else
            <label class="block text-sm">
                {{ __('converter.hijri_year') }}
                <input type="number" wire:model.live="hijriYear" dir="ltr" min="1" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start" data-converter-input="hijri-year">
            </label>
            <label class="block text-sm">
                {{ __('converter.hijri_month') }}
                <select wire:model.live="hijriMonth" class="mt-1 min-h-11 w-full rounded-lg border p-2" data-converter-input="hijri-month">
                    <option value="">{{ __('converter.choose') }}</option>
                    @foreach ($this->hijriMonths as $number => $name)
                        <option value="{{ $number }}">{{ $name }}</option>
                    @endforeach
                </select>
            </label>
            <label class="block text-sm">
                {{ __('converter.hijri_day') }}
                <select wire:model.live="hijriDay" class="mt-1 min-h-11 w-full rounded-lg border p-2" data-converter-input="hijri-day">
                    <option value="">{{ __('converter.choose') }}</option>
                    @for ($day = 1; $day <= 30; $day++)
                        <option value="{{ $day }}">{{ $day }}</option>
                    @endfor
                </select>
            </label>
        @endif
    </form>

    @if ($this->hasCompleteInput)
        @if ($result = $this->result)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900" data-converter-result>
                <p class="text-sm text-gray-500">{{ __('converter.result_hijri') }}</p>
                <p class="text-lg font-semibold" data-converter-hijri>{{ $result['hijri'] }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ __('converter.disclaimer') }}</p>

                <p class="mt-3 text-sm text-gray-500">{{ __('converter.result_gregorian') }}</p>
                <p class="text-lg font-semibold" dir="ltr" data-converter-gregorian>{{ $result['gregorian'] }}</p>

                <p class="mt-1 text-sm text-gray-500" data-converter-weekday>{{ $result['weekday'] }}</p>
            </div>
        @else
            <p class="text-danger-600" data-converter-error>
                {{ $direction === \App\Filament\Pages\Converter::DIRECTION_HIJRI_TO_GREGORIAN
                    ? __('converter.errors.invalid_hijri_day')
                    : __('converter.errors.invalid_gregorian_date') }}
            </p>
        @endif
    @endif
</div>
