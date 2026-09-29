<div class="mx-auto w-full max-w-md px-3 py-8 sm:px-4">
    @if ($challengedUser === null)
        <h1 class="text-2xl font-bold text-ink">تسجيل الدخول</h1>

        <form wire:submit="login" novalidate class="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
            @error('phone')
                <p id="login-error" role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
            @enderror

            <div>
                <label for="phone" class="block text-sm font-medium text-ink">رقم الجوال</label>
                <input
                    id="phone"
                    type="tel"
                    inputmode="tel"
                    dir="ltr"
                    wire:model="phone"
                    autocomplete="tel"
                    placeholder="05XXXXXXXX"
                    @error('phone') aria-invalid="true" aria-describedby="login-error" @enderror
                    class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-start text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
                >
            </div>

            <x-form.password-input name="password" label="كلمة المرور" autocomplete="current-password" />

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
                دخول
            </button>

            <p class="text-center text-sm">
                <a href="{{ route('password.forgot') }}" class="inline-flex min-h-11 items-center font-medium text-pri underline-offset-4 hover:underline">{{ __('recovery.forgot_title') }}</a>
            </p>

            <p class="text-center text-sm text-muted">
                ليس لديك حساب؟
                <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center font-medium text-pri underline-offset-4 hover:underline">أنشئ حسابًا</a>
            </p>
        </form>
    @else
        <h1 class="text-2xl font-bold text-ink">{{ __('auth.two_factor.heading') }}</h1>

        <form wire:submit="verifyTwoFactor" novalidate class="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
            @php($field = $useRecoveryCode ? 'recoveryCode' : 'code')

            <p class="text-sm text-muted">
                {{ $useRecoveryCode ? __('auth.two_factor.recovery_intro') : __('auth.two_factor.intro') }}
            </p>

            @error($field)
                <p id="two-factor-error" role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
            @enderror

            <div>
                @if ($useRecoveryCode)
                    <label for="recoveryCode" class="block text-sm font-medium text-ink">{{ __('auth.two_factor.recovery_code') }}</label>
                    <input
                        id="recoveryCode"
                        type="text"
                        dir="ltr"
                        wire:model="recoveryCode"
                        autocomplete="one-time-code"
                        spellcheck="false"
                        @error('recoveryCode') aria-invalid="true" aria-describedby="two-factor-error" @enderror
                        class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-start text-base text-ink focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
                    >
                @else
                    <label for="code" class="block text-sm font-medium text-ink">{{ __('auth.two_factor.code') }}</label>
                    <input
                        id="code"
                        type="text"
                        inputmode="numeric"
                        dir="ltr"
                        maxlength="6"
                        wire:model="code"
                        autocomplete="one-time-code"
                        autofocus
                        placeholder="000000"
                        @error('code') aria-invalid="true" aria-describedby="two-factor-error" @enderror
                        class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-center text-lg tracking-[0.3em] text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
                    >
                @endif
            </div>

            <button
                type="submit"
                class="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri data-loading:opacity-60"
            >
                {{ __('auth.two_factor.submit') }}
            </button>

            <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                <button type="button" wire:click="toggleRecoveryCode" class="inline-flex min-h-11 items-center font-medium text-pri underline-offset-4 hover:underline">
                    {{ $useRecoveryCode ? __('auth.two_factor.use_code') : __('auth.two_factor.use_recovery') }}
                </button>

                <button type="button" wire:click="cancelTwoFactor" class="inline-flex min-h-11 items-center font-medium text-muted underline-offset-4 hover:underline">
                    {{ __('auth.two_factor.cancel') }}
                </button>
            </div>
        </form>
    @endif
</div>
