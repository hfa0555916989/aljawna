<div class="mx-auto w-full max-w-md px-3 py-8 sm:px-4">
    <h1 class="text-2xl font-bold text-ink">{{ __('recovery.forgot_title') }}</h1>

    <form wire:submit="submit" novalidate class="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
        @error('phone')
            <p role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
        @enderror

        <div>
            <label for="phone" class="block text-sm font-medium text-ink">{{ __('recovery.phone') }}</label>
            <input id="phone" type="tel" dir="ltr" wire:model="phone" autocomplete="tel" placeholder="05XXXXXXXX" class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-base text-ink">
        </div>

        <button type="submit" class="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink">
            {{ __('recovery.submit') }}
        </button>
    </form>

    @if ($supervisors !== null)
        <section class="mt-6">
            <h2 class="text-lg font-semibold text-ink">{{ __('recovery.supervisors') }}</h2>
            <ul class="mt-3 flex flex-col gap-3">
                @forelse ($supervisors as $supervisor)
                    <li class="flex gap-2">
                        <a href="tel:{{ $supervisor->phone }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-[10px] border border-line px-3 font-medium text-ink">{{ __('recovery.call') }}</a>
                        <a href="https://wa.me/{{ ltrim($supervisor->phone, '+') }}" class="inline-flex min-h-11 flex-1 items-center justify-center rounded-[10px] bg-pri px-3 font-medium text-pri-ink">{{ __('recovery.whatsapp') }}</a>
                    </li>
                @empty
                    <li class="text-sm text-muted">{{ __('recovery.empty') }}</li>
                @endforelse
            </ul>
        </section>
    @endif
</div>
