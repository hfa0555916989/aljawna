<div class="mx-auto max-w-6xl px-4 pt-8">
    <section @if (filled($data['heading'] ?? null)) aria-labelledby="{{ $blockId }}-heading" @endif>
        @if (filled($data['heading'] ?? null))
            <h2 id="{{ $blockId }}-heading" class="text-xl font-bold text-ink">{{ $data['heading'] }}</h2>
        @endif
        <ol @class(['grid gap-4 sm:grid-cols-3', 'mt-4' => filled($data['heading'] ?? null)])>
            @foreach ($data['items'] as $index => $step)
                <li class="rounded-[14px] border border-line bg-surface p-5">
                    <span aria-hidden="true" class="inline-flex size-9 items-center justify-center rounded-full bg-brass-soft text-base font-bold text-ink">{{ $index + 1 }}</span>
                    <h3 class="mt-3 text-base font-bold text-ink">{{ $step['title'] }}</h3>
                    @if (filled($step['body'] ?? null))
                        <p class="mt-1 text-sm leading-6 text-muted">{{ $step['body'] }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </section>
</div>
