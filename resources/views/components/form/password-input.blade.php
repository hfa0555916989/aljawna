@props([
    'name',
    'label',
    'autocomplete' => 'current-password',
])

<div x-data="{ shown: false }">
    <label for="{{ $name }}" class="block text-sm font-medium text-ink">{{ $label }}</label>

    <div class="relative mt-1.5">
        <input
            id="{{ $name }}"
            type="password"
            x-bind:type="shown ? 'text' : 'password'"
            wire:model="{{ $name }}"
            autocomplete="{{ $autocomplete }}"
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
            {{ $attributes->class('block w-full min-h-11 rounded-[10px] border border-line bg-surface py-2 ps-3 pe-20 text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad') }}
        >

        <button
            type="button"
            x-on:click="shown = ! shown"
            x-bind:aria-pressed="shown.toString()"
            aria-controls="{{ $name }}"
            class="absolute inset-y-0 end-0 flex min-h-11 min-w-16 items-center justify-center rounded-e-[10px] px-3 text-sm font-medium text-pri hover:underline focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-pri"
        >
            <span x-show="! shown">إظهار</span>
            <span x-show="shown" x-cloak>إخفاء</span>
            <span class="sr-only">{{ $label }}</span>
        </button>
    </div>

    {{ $slot }}

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 text-sm text-bad">{{ $message }}</p>
    @enderror
</div>
