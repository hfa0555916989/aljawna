<section class="hero-pattern text-hero-ink">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:py-14">
        @if ($isMainHeading)
            <h1 class="font-brand text-4xl font-bold sm:text-5xl">{{ $data['title'] }}</h1>
        @else
            <h2 class="font-brand text-3xl font-bold sm:text-4xl">{{ $data['title'] }}</h2>
        @endif

        @if (filled($data['lead'] ?? null))
            <p class="mt-4 max-w-2xl text-base leading-8 sm:text-lg">{{ $data['lead'] }}</p>
        @endif

        @if ($system['counts'] !== null)
            <h2 class="sr-only">{{ __('site.home.counters_heading') }}</h2>
            <div class="mt-8 grid max-w-xl grid-cols-2 gap-3 sm:gap-4">
                <a
                    href="{{ route('beneficiaries.index') }}"
                    class="flex flex-col rounded-[14px] border border-hero-ink/20 bg-hero-ink/5 p-4 hover:bg-hero-ink/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-ink sm:p-5"
                >
                    <span class="text-4xl font-bold text-brass sm:text-5xl" dir="ltr" data-counter="available">{{ $system['counts']['available'] }}</span>
                    <span class="mt-1 text-sm font-semibold sm:text-base">{{ __('site.home.available_count') }}</span>
                </a>
                <a
                    href="{{ route('beneficiaries.index', ['tab' => 'closed']) }}"
                    class="flex flex-col rounded-[14px] border border-hero-ink/20 bg-hero-ink/5 p-4 hover:bg-hero-ink/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-ink sm:p-5"
                >
                    <span class="text-4xl font-bold text-brass sm:text-5xl" dir="ltr" data-counter="closed">{{ $system['counts']['closed'] }}</span>
                    <span class="mt-1 text-sm font-semibold sm:text-base">{{ __('site.home.closed_count') }}</span>
                </a>
            </div>
        @endif

        @if ($system['buttons'] !== [])
            <div class="mt-8 flex flex-wrap gap-3">
                @foreach ($system['buttons'] as $button)
                    <a
                        href="{{ $button['url'] }}"
                        @if (\App\Support\SafeLink::isExternal($button['url'])) rel="noopener noreferrer" @endif
                        @class([
                            'inline-flex min-h-11 items-center rounded-[10px] px-5 text-base font-semibold focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-ink',
                            'bg-hero-ink text-hero hover:opacity-90' => $button['style'] === 'primary',
                            'border border-hero-ink/40 text-hero-ink hover:bg-hero-ink/10' => $button['style'] !== 'primary',
                        ])
                    >
                        {{ $button['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</section>
