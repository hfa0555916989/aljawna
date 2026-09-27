<x-layouts::app
    :title="$page->title.' — '.config('app.name')"
    :description="$page->seo_description ?? __('site.meta.description')"
    :noindex="$isPreview"
>
    @if ($isPreview)
        <div role="status" class="border-b border-line bg-brass-soft">
            <p class="mx-auto max-w-6xl px-4 py-3 text-sm font-semibold text-ink">
                {{ __('pages.preview.banner') }}
            </p>
        </div>
    @endif

    <x-page.blocks :blocks="$blocks" :title="$page->title" />
</x-layouts::app>
