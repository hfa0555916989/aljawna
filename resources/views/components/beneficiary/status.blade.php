@props(['beneficiary'])

@php
    $isClosed = $beneficiary->status === \App\BeneficiaryStatus::Closed;
    $isAvailable = $beneficiary->acceptsTransfers();
@endphp

@if ($isClosed || $isAvailable)
    <span {{ $attributes->class([
        'inline-flex shrink-0 items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold',
        'border-good text-good' => $isAvailable,
        'border-line text-muted' => $isClosed,
    ]) }}>
        {{ __('beneficiaries.status.'.$beneficiary->status->value) }}
    </span>
@endif
