<div class="mx-auto w-full max-w-md px-3 py-8 sm:px-4">
    <h1 class="text-2xl font-bold text-ink">الانضمام كمشرف</h1>

    @if ($invalid)
        <p role="alert" class="mt-6 rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">
            {{ __('supervisors.errors.token_used') }}
        </p>
    @else
        <form wire:submit="join" novalidate class="mt-6 flex flex-col gap-5 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
            @error('form')
                <p role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
            @enderror

            <div>
                <label for="phone" class="block text-sm font-medium text-ink">رقم الجوال</label>
                <input
                    id="phone"
                    type="text"
                    dir="ltr"
                    value="{{ $phone }}"
                    readonly
                    class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-base text-ink"
                >
                <p class="mt-1 text-sm text-muted">{{ __('supervisors.join.phone_fixed') }}</p>
            </div>

            <div>
                <label for="full_name" class="block text-sm font-medium text-ink">الاسم الكامل</label>
                <input
                    id="full_name"
                    type="text"
                    wire:model="full_name"
                    autocomplete="name"
                    placeholder="اكتب اسمك كاملًا"
                    class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-base text-ink"
                >
                @error('full_name')
                    <p class="mt-1 text-sm text-bad">{{ $message }}</p>
                @enderror
            </div>

            <x-form.password-input name="password" label="كلمة المرور" autocomplete="new-password" />
            <x-form.password-input name="password_confirmation" label="تأكيد كلمة المرور" autocomplete="new-password" />

            <button type="submit" class="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink">
                {{ __('supervisors.join.submit') }}
            </button>
        </form>
    @endif
</div>
