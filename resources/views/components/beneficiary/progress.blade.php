@props(['beneficiary'])

@php
    $percentage = \App\Support\ProgressPercentage::of('0', $beneficiary->target_amount);
    $labelId = 'progress-'.$beneficiary->id;
@endphp

<div {{ $attributes }}>
    <div class="flex items-baseline justify-between gap-2 text-sm">
        <span id="{{ $labelId }}" class="text-muted">{{ __('site.beneficiaries.progress') }}</span>
        <span dir="ltr" class="font-bold text-ink">{{ $percentage }}%</span>
    </div>
    <progress class="progress-bar mt-1.5" max="100" value="{{ $percentage }}" aria-labelledby="{{ $labelId }}">{{ $percentage }}%</progress>
</div>
