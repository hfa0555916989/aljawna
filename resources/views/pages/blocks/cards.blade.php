<div class="mx-auto max-w-6xl px-4 pt-8">
    <section @if (filled($data['heading'] ?? null)) aria-labelledby="{{ $blockId }}-heading" @endif>
        @if (filled($data['heading'] ?? null))
            <h2 id="{{ $blockId }}-heading" class="text-xl font-bold text-ink">{{ $data['heading'] }}</h2>
        @endif
        <ul @class(['grid gap-4 sm:grid-cols-2 lg:grid-cols-3', 'mt-4' => filled($data['heading'] ?? null)])>
            @foreach ($data['items'] as $item)
                <li class="flex flex-col gap-2 rounded-[14px] border border-line bg-surface p-5">
                    <h3 class="text-base font-bold text-ink">{{ $item['title'] }}</h3>
                    @if (filled($item['body'] ?? null))
                        <p class="text-sm leading-6 text-muted">{{ $item['body'] }}</p>
                    @endif
                    @if (filled($item['link_url'] ?? null))
                        <a
                            href="{{ $item['link_url'] }}"
                            @if (\App\Support\SafeLink::isExternal($item['link_url'])) rel="noopener noreferrer" @endif
                            class="mt-auto inline-flex min-h-11 items-center justify-center rounded-[10px] border border-pri px-4 text-sm font-semibold text-pri hover:bg-brass-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri"
                        >
                            {{ filled($item['link_label'] ?? null) ? $item['link_label'] : __('pages.render.more') }}
                        </a>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
</div>
