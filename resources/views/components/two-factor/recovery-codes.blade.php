@props([
    'codes',
    'continueUrl' => null,
])

{{-- رموز الاسترداد الخام تُعرض مرة واحدة فقط بعد إصدارها، ولا تُحفظ إلا مجزّأة. --}}
<section class="flex flex-col gap-4 rounded-[14px] border border-line bg-surface p-4 sm:p-6" data-recovery-codes>
    <h2 class="text-lg font-semibold text-ink">{{ __('auth.two_factor.recovery_codes.heading') }}</h2>

    <p class="text-sm text-muted">{{ __('auth.two_factor.recovery_codes.intro') }}</p>

    <ul dir="ltr" class="grid gap-2 font-mono text-sm text-ink sm:grid-cols-2">
        @foreach ($codes as $code)
            <li class="select-all break-all rounded-[8px] border border-line px-2 py-1.5 text-center">{{ $code }}</li>
        @endforeach
    </ul>

    @if ($continueUrl !== null)
        <a href="{{ $continueUrl }}" class="flex min-h-11 items-center justify-center rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">
            {{ __('auth.two_factor.recovery_codes.continue') }}
        </a>
    @endif
</section>
