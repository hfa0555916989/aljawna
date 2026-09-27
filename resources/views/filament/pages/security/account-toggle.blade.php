@can(\App\PermissionKey::UsersSuspend->value)
    @if ($togglingId === $account->id)
        <form wire:submit="toggleActive({{ $account->id }})" class="mt-3 flex flex-col gap-3" data-toggle-form="{{ $account->id }}">
            <label class="block text-sm">
                {{ __('security.reason') }}
                <textarea wire:model.live="toggleReason" maxlength="{{ \App\Actions\Users\SetInitiatorActive::REASON_MAX_LENGTH }}" class="mt-1 w-full rounded-lg border p-2" required></textarea>
            </label>
            @error('toggle_reason') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
            <div class="flex flex-wrap gap-2">
                <button type="submit" class="min-h-11 rounded-lg px-3 text-white {{ $account->is_active ? 'bg-danger-600' : 'bg-primary-600' }}" @disabled(trim($toggleReason) === '')>
                    {{ $account->is_active ? __('security.suspend') : __('security.activate') }}
                </button>
                <button type="button" wire:click="cancelToggle" class="min-h-11 rounded-lg border px-3">{{ __('security.cancel') }}</button>
            </div>
        </form>
    @else
        <button type="button" wire:click="startToggle({{ $account->id }})" class="mt-3 min-h-11 rounded-lg border px-3">
            {{ $account->is_active ? __('security.suspend') : __('security.activate') }}
        </button>
    @endif
@endcan
