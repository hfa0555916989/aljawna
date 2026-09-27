<div class="flex flex-col gap-6">
    <p class="text-sm text-gray-500 dark:text-gray-400">{{ __('health.intro') }}</p>

    <section class="grid gap-3 sm:grid-cols-2">
        @foreach ($this->checks as $check)
            <article wire:key="health-{{ $check['key'] }}" data-health-check="{{ $check['key'] }}" data-health-status="{{ $check['status']->value }}" class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="font-semibold">{{ $check['label'] }}</h2>
                    <x-filament::badge :color="$check['status']->getColor()">{{ $check['status']->getLabel() }}</x-filament::badge>
                </div>
                <p class="text-sm">{{ $check['value'] }}</p>
            </article>
        @endforeach
    </section>

    <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
        <h2 class="font-semibold">{{ __('health.versions') }}</h2>
        <dl class="mt-3 grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
            @foreach ($this->versions as $name => $version)
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">{{ $name }}</dt>
                    <dd dir="ltr" class="text-start font-mono">{{ $version }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
