@props(['label', 'value', 'display' => null])

<div x-data="copyField(@js($value))" {{ $attributes->class('min-w-0') }}>
    <dt class="text-sm text-muted">{{ $label }}</dt>
    <dd class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-2">
        <span dir="ltr" class="min-w-0 select-all break-all text-start font-mono text-base font-semibold text-ink sm:text-lg">{{ $display ?? $value }}</span>
        <button
            type="button"
            x-on:click="copy()"
            aria-label="{{ __('site.copy.aria', ['label' => $label]) }}"
            class="inline-flex min-h-11 min-w-16 items-center justify-center rounded-[10px] border border-pri px-4 text-sm font-semibold text-pri hover:bg-brass-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri"
        >
            {{ __('site.copy.button') }}
        </button>
        <span role="status" aria-live="polite" class="text-sm font-semibold text-good">
            <span x-show="state === 'copied'" x-cloak>{{ __('site.copy.copied') }}</span>
        </span>
    </dd>
    <div x-show="state === 'manual'" x-cloak class="mt-2">
        <p role="alert" class="text-sm text-bad">{{ __('site.copy.manual') }}</p>
        <input
            x-ref="manual"
            type="text"
            readonly
            dir="ltr"
            value="{{ $value }}"
            aria-label="{{ $label }}"
            class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-start font-mono text-base text-ink focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40"
        >
    </div>
</div>
