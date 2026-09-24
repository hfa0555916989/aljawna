@props(['status'])

@php
    $isActive = $status === \App\BeneficiaryStatus::Active;
@endphp

<span {{ $attributes->class([
    'inline-flex shrink-0 items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold',
    'border-good text-good' => $isActive,
    'border-line text-muted' => ! $isActive,
]) }}>
    {{ __('beneficiaries.status.'.$status->value) }}
</span>
