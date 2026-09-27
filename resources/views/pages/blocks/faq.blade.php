<div class="mx-auto max-w-6xl px-4 pt-8">
    <section @if (filled($data['heading'] ?? null)) aria-labelledby="{{ $blockId }}-heading" @endif class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
        @if (filled($data['heading'] ?? null))
            <h2 id="{{ $blockId }}-heading" class="mb-2 text-xl font-bold text-ink">{{ $data['heading'] }}</h2>
        @endif
        <div class="divide-y divide-line">
            @foreach ($data['items'] as $item)
                <details class="group py-1">
                    <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 py-2 font-semibold text-ink hover:text-pri focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">
                        <span>{{ $item['question'] }}</span>
                        <span aria-hidden="true" class="shrink-0 text-xl leading-none text-brass transition-transform group-open:rotate-45">+</span>
                    </summary>
                    <p class="pb-3 text-sm leading-7 text-muted">{{ $item['answer'] }}</p>
                </details>
            @endforeach
        </div>
    </section>
</div>
