@props(['label', 'date', 'countdown' => false])

@php
    $dual = \App\Support\HijriDate::dual($date);
@endphp

<div {{ $attributes }}>
    <dt class="text-sm text-muted">{{ $label }}</dt>
    <dd class="mt-0.5">
        @if ($dual)
            <span class="block font-semibold text-ink">{{ $dual['hijri'] }}</span>
            <span class="block text-sm text-muted">{{ $dual['gregorian'] }} · {{ $dual['weekday'] }}</span>
            @if ($countdown)
                <span class="mt-1.5 inline-flex rounded-full bg-brass-soft px-2.5 py-0.5 text-xs font-semibold text-ink">
                    {{ \App\Support\HijriDate::countdown($date) }}
                </span>
            @endif
        @else
            <span class="text-muted">غير محدد</span>
        @endif
    </dd>
</div>
