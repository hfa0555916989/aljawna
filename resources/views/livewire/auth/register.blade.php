<div class="mx-auto w-full max-w-md px-3 py-8 sm:px-4">
    <h1 class="text-2xl font-bold text-ink">إنشاء حساب</h1>

    <form wire:submit="register" novalidate class="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
        @error('form')
            <p role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
        @enderror

        <div class="hidden" aria-hidden="true">
            <label for="website">اترك هذا الحقل فارغًا</label>
            <input id="website" type="text" wire:model="website" tabindex="-1" autocomplete="off">
        </div>

        <div>
            <label for="full_name" class="block text-sm font-medium text-ink">الاسم الكامل</label>
            <input
                id="full_name"
                type="text"
                wire:model="full_name"
                autocomplete="name"
                placeholder="اكتب اسمك كاملًا"
                @error('full_name') aria-invalid="true" aria-describedby="full_name-error" @enderror
                class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
            >
            @error('full_name')
                <p id="full_name-error" class="mt-1.5 text-sm text-bad">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="phone" class="block text-sm font-medium text-ink">رقم الجوال</label>
            <input
                id="phone"
                type="tel"
                inputmode="tel"
                dir="ltr"
                wire:model.live.debounce.400ms="phone"
                autocomplete="tel"
                placeholder="05XXXXXXXX"
                @error('phone') aria-invalid="true" aria-describedby="phone-error" @enderror
                class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-start text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
            >
            @error('phone')
                <p id="phone-error" class="mt-1.5 text-sm text-bad">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="phone_confirmation" class="block text-sm font-medium text-ink">تأكيد رقم الجوال</label>
            <input
                id="phone_confirmation"
                type="tel"
                inputmode="tel"
                dir="ltr"
                wire:model.live.debounce.400ms="phone_confirmation"
                autocomplete="tel"
                placeholder="05XXXXXXXX"
                aria-describedby="phone_confirmation-status @error('phone_confirmation') phone_confirmation-error @enderror"
                @error('phone_confirmation') aria-invalid="true" @enderror
                class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-start text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
            >
            <p id="phone_confirmation-status" aria-live="polite" class="mt-1.5 min-h-5 text-sm">
                @if ($this->phoneConfirmationMatches === true)
                    <span class="text-good"><span aria-hidden="true">✓</span> رقما الجوال متطابقان</span>
                @elseif ($this->phoneConfirmationMatches === false)
                    <span class="text-bad"><span aria-hidden="true">✗</span> رقما الجوال غير متطابقين</span>
                @endif
            </p>
            @error('phone_confirmation')
                <p id="phone_confirmation-error" class="text-sm text-bad">{{ $message }}</p>
            @enderror
        </div>

        <x-form.password-input name="password" label="كلمة المرور" autocomplete="new-password" />

        <x-form.password-input name="password_confirmation" label="تأكيد كلمة المرور" autocomplete="new-password">
            <p aria-live="polite" class="mt-1.5 min-h-5 text-sm">
                <template x-if="$wire.password_confirmation !== ''">
                    <span>
                        <span x-show="$wire.password === $wire.password_confirmation" class="text-good"><span aria-hidden="true">✓</span> كلمتا المرور متطابقتان</span>
                        <span x-show="$wire.password !== $wire.password_confirmation" class="text-bad"><span aria-hidden="true">✗</span> كلمتا المرور غير متطابقتين</span>
                    </span>
                </template>
            </p>
        </x-form.password-input>

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
            إنشاء الحساب
        </button>

        <p class="text-center text-sm text-muted">
            لديك حساب؟
            <a href="{{ route('login') }}" class="inline-flex min-h-11 items-center font-medium text-pri underline-offset-4 hover:underline">سجّل دخولك</a>
        </p>
    </form>
</div>
