<div class="mx-auto w-full max-w-md px-3 py-8 sm:px-4">
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

        <button
            type="submit"
            class="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri data-loading:opacity-60"
        >
            دخول
        </button>

        <p class="text-center text-sm text-muted">
            ليس لديك حساب؟
            <a href="{{ route('register') }}" class="inline-flex min-h-11 items-center font-medium text-pri underline-offset-4 hover:underline">أنشئ حسابًا</a>
        </p>
    </form>
</div>
