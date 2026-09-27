@props([
    'qr',
    'secret',
])

{{-- إعداد تطبيق المصادقة قبل إنشاء الحساب أو الوصول إليه (App\Support\TwoFactorEnrollment). --}}
<fieldset class="flex flex-col gap-4 rounded-[10px] border border-line p-3 sm:p-4">
    <legend class="px-1 text-base font-semibold text-ink">{{ __('auth.two_factor.enroll.heading') }}</legend>

    <p class="text-sm text-muted">{{ __('auth.two_factor.enroll.intro') }}</p>

    <img
        src="{{ $qr }}"
        alt="{{ __('auth.two_factor.enroll.qr_alt') }}"
        width="192"
        height="192"
        class="mx-auto size-48 rounded-[10px] bg-white p-2"
        data-two-factor-qr
    >

    <div class="text-sm">
        <p class="text-muted">{{ __('auth.two_factor.enroll.manual') }}</p>
        <p dir="ltr" class="mt-1 select-all break-all text-center font-mono text-base tracking-wider text-ink" data-two-factor-secret>{{ $secret }}</p>
    </div>

    <div>
        <label for="two_factor_code" class="block text-sm font-medium text-ink">{{ __('auth.two_factor.enroll.code') }}</label>
        <input
            id="two_factor_code"
            type="text"
            inputmode="numeric"
            dir="ltr"
            maxlength="6"
            wire:model="two_factor_code"
            autocomplete="one-time-code"
            placeholder="000000"
            @error('two_factor_code') aria-invalid="true" aria-describedby="two_factor_code-error" @enderror
            class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-center text-lg tracking-[0.3em] text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
        >
        @error('two_factor_code')
            <p id="two_factor_code-error" class="mt-1 text-sm text-bad">{{ $message }}</p>
        @enderror
    </div>
</fieldset>
