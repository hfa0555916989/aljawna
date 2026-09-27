{{-- كتلة نظامية مقفلة: بياناتها من قاعدة المستفيدين عبر App\Services\PageRenderer، بلا أي بيانات بنكية. --}}
<div class="mx-auto max-w-6xl px-4 pt-8">
    @php($heading = filled($data['heading'] ?? null) ? $data['heading'] : __('site.beneficiaries.title'))

    @if (array_key_exists('transfers', $system))
        <section aria-labelledby="{{ $blockId }}-heading" class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
            <h2 id="{{ $blockId }}-heading" class="text-xl font-bold text-ink">{{ $heading }}</h2>
            @if ($system['transfers']->isEmpty())
                <p class="mt-3 text-muted">{{ __('site.home.latest_empty') }}</p>
            @else
                <ul class="mt-4 flex flex-col gap-3">
                    @foreach ($system['transfers'] as $transfer)
                        <li class="flex flex-wrap items-baseline justify-between gap-2 border-b border-line pb-3 text-sm last:border-b-0 last:pb-0">
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
    @else
        <section aria-labelledby="{{ $blockId }}-heading">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 id="{{ $blockId }}-heading" class="text-xl font-bold text-ink">{{ $heading }}</h2>
                <a href="{{ route('beneficiaries.index') }}" class="inline-flex min-h-11 items-center text-sm font-semibold text-pri hover:underline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">
                    {{ __('pages.render.all_initiatives') }}
                </a>
            </div>

            @if ($system['beneficiaries']->isEmpty())
                <p class="mt-4 rounded-[14px] border border-line bg-surface p-6 text-center text-muted">{{ __('site.beneficiaries.empty.available') }}</p>
            @else
                <ul class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($system['beneficiaries'] as $beneficiary)
                        <li class="flex flex-col gap-4 rounded-[14px] border border-line bg-surface p-4 sm:p-5">
                            <div class="flex items-start justify-between gap-3">
                                <h3 class="min-w-0 break-words text-lg font-bold leading-7 text-ink">
                                    <a href="{{ route('beneficiaries.show', $beneficiary) }}" class="hover:text-pri focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">{{ $beneficiary->display_name }}</a>
                                </h3>
                                <x-beneficiary.status :beneficiary="$beneficiary" />
                            </div>

                            <div>
                                <x-beneficiary.progress :beneficiary="$beneficiary" />
                                <p class="mt-1.5 text-sm text-muted">{{ __('site.beneficiaries.target', ['amount' => \App\Support\Money::format($beneficiary->target_amount)]) }}</p>
                            </div>

                            <dl class="grid gap-3 border-t border-line pt-4">
                                <x-dual-date :label="__('beneficiaries.fields.target_deadline')" :date="$beneficiary->target_deadline" :countdown="true" />
                            </dl>

                            <a
                                href="{{ route('beneficiaries.show', $beneficiary) }}"
                                class="mt-auto inline-flex min-h-11 items-center justify-center rounded-[10px] border border-pri px-4 text-sm font-semibold text-pri hover:bg-brass-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri"
                            >
                                {{ __('site.beneficiaries.details') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
