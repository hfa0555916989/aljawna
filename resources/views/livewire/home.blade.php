<div>
    <section class="hero-pattern text-hero-ink">
        <div class="mx-auto max-w-6xl px-4 py-10 sm:py-14">
            <h1 class="font-brand text-4xl font-bold sm:text-5xl">{{ config('app.name') }}</h1>
            <p class="mt-4 max-w-2xl text-base leading-8 sm:text-lg">{{ __('site.home.lead') }}</p>

            <h2 class="sr-only">{{ __('site.home.counters_heading') }}</h2>
            <div class="mt-8 grid max-w-xl grid-cols-2 gap-3 sm:gap-4">
                <a
                    href="{{ route('beneficiaries.index') }}"
                    class="flex flex-col rounded-[14px] border border-hero-ink/20 bg-hero-ink/5 p-4 hover:bg-hero-ink/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-ink sm:p-5"
                >
                    <span class="text-4xl font-bold text-brass sm:text-5xl" dir="ltr" data-counter="available">{{ $this->availableCount }}</span>
                    <span class="mt-1 text-sm font-semibold sm:text-base">{{ __('site.home.available_count') }}</span>
                </a>
                <a
                    href="{{ route('beneficiaries.index', ['tab' => 'closed']) }}"
                    class="flex flex-col rounded-[14px] border border-hero-ink/20 bg-hero-ink/5 p-4 hover:bg-hero-ink/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-ink sm:p-5"
                >
                    <span class="text-4xl font-bold text-brass sm:text-5xl" dir="ltr" data-counter="closed">{{ $this->closedCount }}</span>
                    <span class="mt-1 text-sm font-semibold sm:text-base">{{ __('site.home.closed_count') }}</span>
                </a>
            </div>

            <div class="mt-8 flex flex-wrap gap-3">
                <a
                    href="{{ route('beneficiaries.index') }}"
                    class="inline-flex min-h-11 items-center rounded-[10px] bg-hero-ink px-5 text-base font-semibold text-hero hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-ink"
                >
                    {{ __('site.home.browse') }}
                </a>
                @guest
                    <a
                        href="{{ route('register') }}"
                        class="inline-flex min-h-11 items-center rounded-[10px] border border-hero-ink/40 px-5 text-base font-semibold text-hero-ink hover:bg-hero-ink/10 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-hero-ink"
                    >
                        {{ __('site.home.register') }}
                    </a>
                @endguest
            </div>
        </div>
    </section>

    <div class="mx-auto flex max-w-6xl flex-col gap-8 px-4 py-10">
        <section aria-labelledby="about-heading" class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
            <h2 id="about-heading" class="text-xl font-bold text-ink">{{ __('site.home.about_heading') }}</h2>
            <ul class="mt-4 flex flex-col gap-3 text-base leading-7 text-ink">
                @foreach (__('site.home.about') as $index => $line)
                    <li wire:key="about-{{ $index }}" class="flex gap-3">
                        <span aria-hidden="true" class="mt-2.5 size-2 shrink-0 rounded-full bg-brass"></span>
                        <span>{{ $line }}</span>
                    </li>
                @endforeach
            </ul>
        </section>

        <section aria-labelledby="how-heading">
            <h2 id="how-heading" class="text-xl font-bold text-ink">{{ __('site.home.how_heading') }}</h2>
            <ol class="mt-4 grid gap-4 sm:grid-cols-3">
                @foreach (__('site.home.steps') as $index => $step)
                    <li wire:key="step-{{ $index }}" class="rounded-[14px] border border-line bg-surface p-5">
                        <span aria-hidden="true" class="inline-flex size-9 items-center justify-center rounded-full bg-brass-soft text-base font-bold text-ink">{{ $index + 1 }}</span>
                        <h3 class="mt-3 text-base font-bold text-ink">{{ $step['title'] }}</h3>
                        <p class="mt-1 text-sm leading-6 text-muted">{{ $step['body'] }}</p>
                    </li>
                @endforeach
            </ol>
        </section>

        <section aria-labelledby="latest-heading" class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
            <h2 id="latest-heading" class="text-xl font-bold text-ink">{{ __('site.home.latest_heading') }}</h2>
            @if ($this->recentTransfers->isEmpty())
                <p class="mt-3 text-muted">{{ __('site.home.latest_empty') }}</p>
            @else
                <ul class="mt-4 flex flex-col gap-3">
                    @foreach ($this->recentTransfers as $transfer)
                        <li wire:key="recent-{{ $transfer->id }}" class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line pb-3 text-sm last:border-b-0 last:pb-0">
                            <span class="text-ink">
                                <span class="font-semibold">{{ $transfer->user->firstName() }}</span>
                                {{ __('site.home.latest_transferred') }}
                                <span class="font-semibold" dir="ltr">{{ \App\Support\Money::format($transfer->amount) }}</span>
                                {{ __('site.home.latest_supporting') }}
                                <span class="font-semibold">{{ $transfer->beneficiary->display_name }}</span>
                            </span>
                            <span class="shrink-0 text-xs text-muted">{{ $transfer->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
