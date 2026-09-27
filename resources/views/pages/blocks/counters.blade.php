<div class="mx-auto max-w-6xl px-4 pt-8">
    <section @if (filled($data['heading'] ?? null)) aria-labelledby="{{ $blockId }}-heading" @endif>
        @if (filled($data['heading'] ?? null))
            <h2 id="{{ $blockId }}-heading" class="text-xl font-bold text-ink">{{ $data['heading'] }}</h2>
        @endif
        <dl @class(['grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4', 'mt-4' => filled($data['heading'] ?? null)])>
            @foreach ($data['metrics'] as $metric)
                <div class="flex flex-col-reverse rounded-[14px] border border-line bg-surface p-4 sm:p-5">
                    <dt class="mt-1 text-sm font-semibold text-muted">{{ __('pages.metrics.'.$metric) }}</dt>
                    <dd class="text-3xl font-bold text-pri sm:text-4xl" dir="ltr" data-metric="{{ $metric }}">{{ $system['values'][$metric] }}</dd>
                </div>
            @endforeach
        </dl>
    </section>
</div>
