@props(['blocks', 'title' => null])

{{-- كتل الصفحة بعد تجهيزها في App\Services\PageRenderer. أول "عنوان رئيسي" هو h1، وإن لم يوجد فعنوان الصفحة. --}}
@php($firstHeroIndex = collect($blocks)->search(fn (array $block): bool => $block['type'] === \App\Support\PageBlocks::HERO))

<div>
    @if ($firstHeroIndex === false && filled($title))
        <div class="mx-auto max-w-6xl px-4 pt-8">
            <h1 class="text-2xl font-bold text-ink sm:text-3xl">{{ $title }}</h1>
        </div>
    @endif

    @foreach ($blocks as $index => $block)
        @include('pages.blocks.'.$block['type'], [
            'data' => $block['data'],
            'system' => $block['system'],
            'blockId' => 'block-'.$index,
            'isMainHeading' => $index === $firstHeroIndex,
        ])
    @endforeach
</div>
