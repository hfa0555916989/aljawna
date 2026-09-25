<div class="flex flex-col gap-4">
    @forelse ($this->entries as $entry)
        @php($when = \App\Support\HijriDate::dual($entry->created_at))
        <article wire:key="recovery-log-{{ $entry->id }}" class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500">{{ __('recovery.log_action') }}</p>
            <p class="font-semibold">{{ __('recovery.actions.'.$entry->action->value) }}</p>
            <p class="mt-2 text-sm text-gray-500">{{ __('recovery.log_user') }}</p>
            <p>{{ $entry->user?->full_name }}</p>
            <p dir="ltr" class="text-start font-mono">{{ $entry->user?->phone }}</p>
            <p class="mt-2 text-sm text-gray-500">{{ __('recovery.log_supervisor') }}</p>
            <p>{{ $entry->performer?->full_name }}</p>
            @if ($entry->old_phone || $entry->new_phone)
                <p dir="ltr" class="mt-2 text-start font-mono">{{ $entry->old_phone }} → {{ $entry->new_phone }}</p>
            @endif
            @if ($entry->sent_to_phone)
                <p dir="ltr" class="mt-2 text-start font-mono">{{ $entry->sent_to_phone }}</p>
            @endif
            @if ($entry->reason)
                <p class="mt-2">{{ $entry->reason }}</p>
            @endif
            @if ($when)
                <p class="mt-2 text-sm">{{ $when['hijri'] }} — {{ $when['gregorian'] }}</p>
            @endif
        </article>
    @empty
        <p>{{ __('recovery.log_empty') }}</p>
    @endforelse
</div>
