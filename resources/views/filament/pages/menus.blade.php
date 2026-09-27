<x-filament-panels::page>
    <form wire:submit="save" class="flex flex-col gap-6">
        {{ $this->form }}

        <div>
            <x-filament::button type="submit" data-menus-save>
                {{ __('pages.menus.save') }}
            </x-filament::button>
        </div>
    </form>

    <x-filament::section>
        <x-slot name="heading">{{ __('pages.menus.revisions') }}</x-slot>
        <x-slot name="description">{{ __('pages.menus.revisions_description') }}</x-slot>

        <ul class="divide-y divide-gray-200 dark:divide-white/10">
            @foreach ($this->revisions as $revision)
                <li wire:key="menu-revision-{{ $revision->id }}" class="flex flex-wrap items-center justify-between gap-3 py-3">
                    <div class="flex flex-wrap items-center gap-2 text-sm">
                        <span class="font-semibold" dir="ltr">#{{ $revision->id }}</span>
                        <span class="text-gray-500 dark:text-gray-400" dir="ltr">{{ $revision->created_at->timezone('Asia/Riyadh')->format('Y-m-d H:i') }}</span>
                        <span>{{ $revision->author?->full_name ?? __('pages.revisions.system') }}</span>
                        @if ($revision->is_baseline)
                            <x-filament::badge color="warning">{{ __('pages.revisions.baseline') }}</x-filament::badge>
                        @endif
                        <span class="text-gray-500 dark:text-gray-400">{{ trans_choice('pages.menus.items_count', count($revision->items), ['count' => count($revision->items)]) }}</span>
                    </div>
                    {{ ($this->restoreRevisionAction)(['revision' => $revision->id]) }}
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-panels::page>
