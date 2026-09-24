<div class="flex flex-col gap-4">
    @forelse ($this->requests as $request)
        <article wire:key="recovery-{{ $request->id }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="font-semibold">{{ $request->user?->full_name }}</p>
            <p dir="ltr" class="text-start font-mono">{{ $request->user?->phone }}</p>
            <p class="mt-1 text-sm">{{ __('recovery.statuses.'.$request->status->value) }}</p>

            <div class="mt-3 flex flex-wrap gap-2">
                @if ($request->status === \App\PasswordResetStatus::Pending || ($request->status === \App\PasswordResetStatus::Claimed && ! $request->isClaimedBy(auth()->user())))
                    <button type="button" wire:click="claim({{ $request->id }})" class="min-h-11 rounded-lg bg-primary-600 px-3 text-white">{{ __('recovery.claim') }}</button>
                @endif

                @if ($request->isClaimedBy(auth()->user()))
                    <button type="button" wire:click="$set('sendingId', {{ $request->id }})" class="min-h-11 rounded-lg bg-primary-600 px-3 text-white">{{ __('recovery.send') }}</button>
                @endif

                <button type="button" wire:click="cancel({{ $request->id }})" class="min-h-11 rounded-lg border px-3">{{ __('recovery.cancel') }}</button>
            </div>

            @if ($sendingId === $request->id && $request->isClaimedBy(auth()->user()))
                <form wire:submit="send({{ $request->id }})" class="mt-4 flex flex-col gap-3">
                    <label class="flex min-h-11 items-center gap-2">
                        <input type="radio" wire:model.live="destination" value="registered">
                        {{ __('recovery.registered_number') }}
                    </label>
                    <label class="flex min-h-11 items-center gap-2">
                        <input type="radio" wire:model.live="destination" value="other">
                        {{ __('recovery.other_number') }}
                    </label>
                    @if ($destination === 'other')
                        <label class="block text-sm">{{ __('recovery.reason') }}
                            <textarea wire:model="reason" class="mt-1 w-full rounded-lg border p-2" required></textarea>
                        </label>
                        @error('reason') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                        <label class="block text-sm">{{ __('recovery.other_phone') }}
                            <input wire:model="otherPhone" dir="ltr" class="mt-1 w-full rounded-lg border p-2">
                        </label>
                        @error('other_phone') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    @endif
                    <button type="submit" class="min-h-11 rounded-lg bg-primary-600 px-3 text-white" @if ($destination === 'other') @disabled(trim($reason) === '') @endif>
                        {{ __('recovery.send') }}
                    </button>
                </form>
            @endif
        </article>
    @empty
        <p>{{ __('recovery.empty') }}</p>
    @endforelse
</div>
