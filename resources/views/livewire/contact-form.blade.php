@php($uid = $this->getId())

<div class="mx-auto mt-6 max-w-xl">
    @if ($sent)
        <div role="status" class="rounded-[14px] border border-good bg-good/10 p-4 text-sm font-semibold text-ink">
            {{ __('contact.sent') }}
        </div>
    @else
        <form wire:submit="send" novalidate class="flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
            @error('form')
                <p role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
            @enderror

            <div class="hidden" aria-hidden="true">
                <label for="website-{{ $uid }}">اترك هذا الحقل فارغًا</label>
                <input id="website-{{ $uid }}" type="text" wire:model="website" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="name-{{ $uid }}" class="block text-sm font-medium text-ink">{{ __('contact.fields.name') }}</label>
                <input
                    id="name-{{ $uid }}"
                    type="text"
                    wire:model="name"
                    autocomplete="name"
                    maxlength="150"
                    @error('name') aria-invalid="true" aria-describedby="name-{{ $uid }}-error" @enderror
                    class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
                >
                @error('name')
                    <p id="name-{{ $uid }}-error" class="mt-1.5 text-sm text-bad">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="phone-{{ $uid }}" class="block text-sm font-medium text-ink">{{ __('contact.fields.phone') }}</label>
                <input
                    id="phone-{{ $uid }}"
                    type="tel"
                    inputmode="tel"
                    dir="ltr"
                    wire:model="phone"
                    autocomplete="tel"
                    placeholder="05XXXXXXXX"
                    @error('phone') aria-invalid="true" aria-describedby="phone-{{ $uid }}-error" @enderror
                    class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-start text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
                >
                @error('phone')
                    <p id="phone-{{ $uid }}-error" class="mt-1.5 text-sm text-bad">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="body-{{ $uid }}" class="block text-sm font-medium text-ink">{{ __('contact.fields.body') }}</label>
                <textarea
                    id="body-{{ $uid }}"
                    wire:model="body"
                    rows="4"
                    maxlength="1000"
                    @error('body') aria-invalid="true" aria-describedby="body-{{ $uid }}-error" @enderror
                    class="mt-1.5 block w-full rounded-[10px] border border-line bg-surface px-3 py-2 text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
                ></textarea>
                @error('body')
                    <p id="body-{{ $uid }}-error" class="mt-1.5 text-sm text-bad">{{ $message }}</p>
                @enderror
            </div>

            @if ($turnstileSiteKey)
                <div>
                    <div
                        wire:ignore
                        x-data
                        x-init="window.renderTurnstile($el, @js($turnstileSiteKey), (token) => { $wire.turnstileToken = token })"
                        x-on:turnstile-reset.window="window.turnstile && window.turnstile.reset($el)"
                    ></div>
                    @error('turnstile')
                        <p role="alert" class="mt-1.5 text-sm text-bad">{{ $message }}</p>
                    @enderror
                </div>

                @assets
                    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
                @endassets
            @endif

            <button
                type="submit"
                class="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri data-loading:opacity-60"
            >
                {{ __('contact.fields.submit') }}
            </button>
        </form>
    @endif
</div>
