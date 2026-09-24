@php
    $inputClass = 'mt-1.5 block w-full min-h-11 rounded-[10px] border border-line bg-surface px-3 py-2 text-base text-ink placeholder:text-muted focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad';
@endphp

<div class="mx-auto w-full max-w-2xl px-3 py-8 sm:px-4">
    <h1 class="text-2xl font-bold text-ink">{{ __('transfers.create.title') }}</h1>
    <p class="mt-2 leading-7 text-muted">{{ __('transfers.create.lead') }}</p>

    @if ($this->dailyLimitReached)
        <p role="alert" class="mt-6 rounded-[14px] border border-bad bg-surface p-5 text-bad">
            {{ __('transfers.create.daily_limit_reached', ['limit' => (int) config('security.transfers.daily_limit')]) }}
        </p>
    @elseif ($this->beneficiaries->isEmpty())
        <p class="mt-6 rounded-[14px] border border-line bg-surface p-6 text-center text-muted">
            {{ __('transfers.create.no_beneficiaries') }}
        </p>
    @else
        <form wire:submit="save" novalidate class="mt-6 flex flex-col gap-6 rounded-[14px] border border-line bg-surface p-4 sm:p-6">
            @error('form')
                <p role="alert" class="rounded-[10px] border border-bad px-3 py-2 text-sm text-bad">{{ $message }}</p>
            @enderror

            <fieldset @error('beneficiary_id') aria-describedby="beneficiary_id-error" @enderror>
                <legend class="text-sm font-medium text-ink">{{ __('transfers.create.beneficiary_legend') }}</legend>
                <div class="mt-2 grid gap-2">
                    @foreach ($this->beneficiaries as $beneficiary)
                        <label wire:key="beneficiary-option-{{ $beneficiary->id }}" class="flex min-h-11 cursor-pointer items-start gap-3 rounded-[10px] border border-line px-3 py-2.5 has-checked:border-pri has-checked:bg-brass-soft has-focus-visible:ring-2 has-focus-visible:ring-pri/40">
                            <input type="radio" wire:model="beneficiary_id" name="beneficiary_id" value="{{ $beneficiary->id }}" class="mt-1 size-4 shrink-0 accent-pri">
                            <span class="min-w-0 break-words font-semibold leading-6 text-ink">{{ $beneficiary->display_name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('beneficiary_id')
                    <p id="beneficiary_id-error" class="mt-1.5 text-sm text-bad">{{ $message }}</p>
                @enderror
            </fieldset>

            <div class="grid gap-6 sm:grid-cols-2">
                <div>
                    <label for="amount" class="block text-sm font-medium text-ink">{{ __('transfers.fields.amount') }} ({{ __('beneficiaries.currency') }})</label>
                    <input
                        id="amount"
                        type="text"
                        inputmode="decimal"
                        dir="ltr"
                        wire:model="amount"
                        autocomplete="off"
                        placeholder="500"
                        aria-describedby="amount-hint @error('amount') amount-error @enderror"
                        @error('amount') aria-invalid="true" @enderror
                        class="{{ $inputClass }} text-start"
                    >
                    <p id="amount-hint" class="mt-1.5 text-sm text-muted">{{ __('transfers.create.amount_hint') }}</p>
                    @error('amount')
                        <p id="amount-error" class="mt-1 text-sm text-bad">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="transferred_on" class="block text-sm font-medium text-ink">{{ __('transfers.fields.transferred_on') }}</label>
                    <input
                        id="transferred_on"
                        type="date"
                        dir="ltr"
                        max="{{ $today }}"
                        wire:model.live="transferred_on"
                        aria-describedby="transferred_on-hijri @error('transferred_on') transferred_on-error @enderror"
                        @error('transferred_on') aria-invalid="true" @enderror
                        class="{{ $inputClass }} text-start"
                    >
                    <p id="transferred_on-hijri" aria-live="polite" class="mt-1.5 min-h-5 text-sm text-muted">
                        @if ($this->transferredOnHijri)
                            {{ __('beneficiaries.hints.hijri', ['date' => $this->transferredOnHijri]) }}
                        @endif
                    </p>
                    @error('transferred_on')
                        <p id="transferred_on-error" class="mt-1 text-sm text-bad">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="bank_reference" class="block text-sm font-medium text-ink">{{ __('transfers.create.bank_reference_optional') }}</label>
                <input
                    id="bank_reference"
                    type="text"
                    dir="ltr"
                    wire:model="bank_reference"
                    autocomplete="off"
                    aria-describedby="bank_reference-hint @error('bank_reference') bank_reference-error @enderror"
                    @error('bank_reference') aria-invalid="true" @enderror
                    class="{{ $inputClass }} text-start"
                >
                <p id="bank_reference-hint" class="mt-1.5 text-sm text-muted">{{ __('transfers.create.bank_reference_hint') }}</p>
                @error('bank_reference')
                    <p id="bank_reference-error" class="mt-1 text-sm text-bad">{{ $message }}</p>
                @enderror
            </div>

            <div
                x-data="{ uploading: false, progress: 0 }"
                x-on:livewire-upload-start="uploading = true; progress = 0"
                x-on:livewire-upload-finish="uploading = false"
                x-on:livewire-upload-cancel="uploading = false"
                x-on:livewire-upload-error="uploading = false"
                x-on:livewire-upload-progress="progress = $event.detail.progress"
            >
                <label for="receipt" class="block text-sm font-medium text-ink">{{ __('transfers.fields.receipt') }}</label>
                <input
                    id="receipt"
                    type="file"
                    wire:model="receipt"
                    accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                    aria-describedby="receipt-hint @error('receipt') receipt-error @enderror"
                    @error('receipt') aria-invalid="true" @enderror
                    class="mt-1.5 block w-full min-h-11 rounded-[10px] border border-dashed border-line bg-surface p-2 text-sm text-ink file:me-3 file:min-h-10 file:cursor-pointer file:rounded-[8px] file:border-0 file:bg-pri file:px-4 file:text-sm file:font-semibold file:text-pri-ink focus:border-pri focus:outline-none focus-visible:ring-2 focus-visible:ring-pri/40 aria-invalid:border-bad"
                >
                <p id="receipt-hint" class="mt-1.5 text-sm text-muted">{{ __('transfers.create.receipt_hint') }}</p>
                <div x-show="uploading" x-cloak class="mt-2">
                    <p class="text-sm text-muted">{{ __('transfers.create.uploading') }}</p>
                    <progress class="progress-bar mt-1" max="100" x-bind:value="progress"></progress>
                </div>
                @error('receipt')
                    <p id="receipt-error" class="mt-1 text-sm text-bad">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save,receipt"
                class="min-h-11 rounded-[10px] bg-pri px-4 py-2 text-base font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri disabled:opacity-60 data-loading:opacity-60"
            >
                {{ __('transfers.create.submit') }}
            </button>
        </form>
    @endif
</div>
