<div class="flex flex-col gap-6">
    <form class="grid gap-3 sm:grid-cols-2" wire:submit.prevent>
        <label class="block text-sm">
            {{ __('recovery.stats.period') }}
            <select wire:model.live="period" class="mt-1 min-h-11 w-full rounded-lg border p-2">
                <option value="day">{{ __('recovery.stats.periods.day') }}</option>
                <option value="month">{{ __('recovery.stats.periods.month') }}</option>
                <option value="year">{{ __('recovery.stats.periods.year') }}</option>
                <option value="custom">{{ __('recovery.stats.periods.custom') }}</option>
            </select>
        </label>

        @if ($period === 'month')
            <label class="block text-sm">
                {{ __('recovery.stats.month') }}
                <input type="month" wire:model.live="month" dir="ltr" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start">
            </label>
        @elseif ($period === 'year')
            <label class="block text-sm">
                {{ __('recovery.stats.year') }}
                <input type="number" wire:model.live="year" dir="ltr" min="2000" max="2100" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start">
            </label>
        @elseif ($period === 'custom')
            <label class="block text-sm">
                {{ __('recovery.stats.start_date') }}
                <input type="date" wire:model.live="startDate" dir="ltr" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start">
            </label>
            <label class="block text-sm">
                {{ __('recovery.stats.end_date') }}
                <input type="date" wire:model.live="endDate" dir="ltr" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start">
            </label>
        @else
            <label class="block text-sm">
                {{ __('recovery.stats.date') }}
                <input type="date" wire:model.live="date" dir="ltr" class="mt-1 min-h-11 w-full rounded-lg border p-2 text-start">
            </label>
        @endif
    </form>

    @php([$rangeStart, $rangeEnd] = $this->range())
    <p class="text-sm text-gray-500" data-recovery-stats-range>
        {{ __('recovery.stats.range', [
            'start' => \App\Support\HijriDate::gregorian($rangeStart),
            'end' => \App\Support\HijriDate::gregorian($rangeEnd->subDay()),
        ]) }}
    </p>

    @php($summary = $this->summary())

    <div class="grid gap-3 sm:grid-cols-3" data-recovery-stats-totals>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500">{{ __('recovery.stats.totals.completed') }}</p>
            <p class="text-2xl font-semibold" data-recovery-stats-total="completed">{{ $summary['totals']['completed'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500">{{ __('recovery.stats.totals.links_issued') }}</p>
            <p class="text-2xl font-semibold" data-recovery-stats-total="links_issued">{{ $summary['totals']['links_issued'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
            <p class="text-sm text-gray-500">{{ __('recovery.stats.totals.phone_changed') }}</p>
            <p class="text-2xl font-semibold" data-recovery-stats-total="phone_changed">{{ $summary['totals']['phone_changed'] }}</p>
        </div>
    </div>

    <div class="flex flex-col gap-3">
        <h2 class="font-semibold">{{ __('recovery.stats.by_supervisor') }}</h2>

        @forelse ($summary['by_supervisor'] as $row)
            <article
                wire:key="recovery-stats-{{ $row['id'] }}"
                data-recovery-stats-supervisor="{{ $row['id'] }}"
                class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900"
            >
                <p class="font-semibold">{{ $row['name'] }}</p>
                <dl class="mt-2 grid grid-cols-2 gap-2 text-sm sm:grid-cols-4">
                    <div>
                        <dt class="text-gray-500">{{ __('recovery.actions.link_registered') }}</dt>
                        <dd data-recovery-stats-cell="link_registered">{{ $row['link_registered'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('recovery.actions.link_other') }}</dt>
                        <dd data-recovery-stats-cell="link_other">{{ $row['link_other'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('recovery.actions.phone_changed') }}</dt>
                        <dd data-recovery-stats-cell="phone_changed">{{ $row['phone_changed'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500">{{ __('recovery.stats.totals.completed') }}</dt>
                        <dd data-recovery-stats-cell="completed">{{ $row['completed'] }}</dd>
                    </div>
                </dl>
                <p class="mt-2 text-sm text-gray-500">
                    {{ __('recovery.stats.total') }}:
                    <span class="font-semibold" data-recovery-stats-cell="total">{{ $row['total'] }}</span>
                </p>
            </article>
        @empty
            <p>{{ __('recovery.stats.empty') }}</p>
        @endforelse
    </div>
</div>
