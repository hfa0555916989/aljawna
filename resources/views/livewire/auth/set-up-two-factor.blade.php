<div class="mx-auto w-full max-w-md px-3 py-8 sm:px-4">
    <h1 class="text-2xl font-bold text-ink">{{ __('auth.two_factor.setup_link.title') }}</h1>

    @if ($recoveryCodes !== [])
        <div class="mt-6">
            <x-two-factor.recovery-codes :codes="$recoveryCodes" :continue-url="auth()->user()?->homeUrl()" />
        </div>
    @elseif ($invalid || $enrollment === null)
        <p role="alert" class="mt-6 rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">
            {{ __('auth.two_factor.setup_link.invalid') }}
        </p>
    @else
        <form wire:submit="setUp" novalidate class="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
            <p class="text-sm text-muted">{{ __('auth.two_factor.setup_link.intro') }}</p>

            @error('form')
                <p role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
            @enderror

            <x-form.password-input name="password" label="كلمة المرور" autocomplete="current-password" />

            <x-two-factor.enroll :qr="$enrollment['qr']" :secret="$enrollment['secret']" />

            <button type="submit" class="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri data-loading:opacity-60">
                {{ __('auth.two_factor.setup_link.submit') }}
            </button>
        </form>
    @endif
</div>
