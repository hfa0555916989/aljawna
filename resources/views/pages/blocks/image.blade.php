<div class="mx-auto max-w-6xl px-4 pt-8">
    @if ($system['url'] !== null)
        <figure>
            <img src="{{ $system['url'] }}" alt="{{ $data['alt'] }}" loading="lazy" class="w-full rounded-[14px] border border-line object-cover">
            @if (filled($data['caption'] ?? null))
                <figcaption class="mt-2 text-center text-sm text-muted">{{ $data['caption'] }}</figcaption>
            @endif
        </figure>
    @endif
</div>
