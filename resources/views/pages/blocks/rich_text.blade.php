<div class="mx-auto max-w-6xl px-4 pt-8">
    <section @if (filled($data['heading'] ?? null)) aria-labelledby="{{ $blockId }}-heading" @endif class="rounded-[14px] border border-line bg-surface p-5 sm:p-6">
        @if (filled($data['heading'] ?? null))
            <h2 id="{{ $blockId }}-heading" class="mb-4 text-xl font-bold text-ink">{{ $data['heading'] }}</h2>
        @endif
        {{-- مُنظَّف على الخادم بقائمة سماح صارمة عند الحفظ وعند العرض (App\Support\SafeHtml). --}}
        <div class="page-prose">{!! $system['html'] !!}</div>
    </section>
</div>
