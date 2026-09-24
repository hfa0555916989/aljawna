<div class="mx-auto w-full max-w-md px-3 py-8 sm:px-4">
    <h1 class="text-2xl font-bold text-ink">{{ __('recovery.reset_title') }}</h1>

    @if ($invalid)
        <p role="alert" class="mt-6 rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ __('recovery.errors.token_used') }}</p>
    @else
        <form wire:submit="resetPassword" novalidate class="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
            @error('form')
                <p role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
            @enderror

            <x-form.password-input name="password" :label="__('recovery.password')" autocomplete="new-password" />
            <x-form.password-input name="password_confirmation" :label="__('recovery.password_confirmation')" autocomplete="new-password" />

            <button type="submit" class="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink">
                {{ __('recovery.reset_submit') }}
            </button>
        </form>
    @endif
</div>
