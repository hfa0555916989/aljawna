<div class="flex max-w-xl flex-col gap-6">
    @if ($recoveryCodes !== [])
        <x-filament::section>
            <x-slot name="heading">{{ __('auth.two_factor.recovery_codes.heading') }}</x-slot>

            <div class="flex flex-col gap-4" data-recovery-codes>
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('auth.two_factor.recovery_codes.regenerated') }} {{ __('auth.two_factor.recovery_codes.intro') }}</p>

                <ul dir="ltr" class="grid gap-2 font-mono text-sm sm:grid-cols-2">
                    @foreach ($recoveryCodes as $recoveryCode)
                        <li class="select-all break-all rounded-lg border border-gray-200 px-2 py-1.5 text-center dark:border-white/10">{{ $recoveryCode }}</li>
                    @endforeach
                </ul>
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <form wire:submit="regenerate" class="flex flex-col gap-4">
                <p class="text-sm text-gray-600 dark:text-gray-300">{{ __('auth.two_factor.recovery_codes.regenerate_intro') }}</p>

                <p class="text-sm" data-recovery-remaining>{{ __('auth.two_factor.recovery_codes.remaining', ['count' => $this->remainingCount()]) }}</p>

                <label class="block text-sm">
                    {{ __('auth.two_factor.code') }}
                    <input
                        type="text"
                        inputmode="numeric"
                        dir="ltr"
                        maxlength="6"
                        wire:model="code"
                        autocomplete="one-time-code"
                        placeholder="000000"
                        class="mt-1 min-h-11 w-full rounded-lg border p-2 text-center text-lg tracking-[0.3em]"
                    >
                </label>

                @error('code')
                    <p role="alert" class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                @enderror

                <div>
                    <x-filament::button type="submit">{{ __('auth.two_factor.recovery_codes.regenerate_submit') }}</x-filament::button>
                </div>
            </form>
        </x-filament::section>
    @endif
</div>
