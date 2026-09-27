{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
    </url>
    <url>
        <loc>{{ route('beneficiaries.index') }}</loc>
    </url>
@foreach ($pages as $page)
    <url>
        <loc>{{ route('pages.show', ['slug' => $page->slug]) }}</loc>
        <lastmod>{{ ($page->published_at ?? $page->updated_at)?->toAtomString() }}</lastmod>
    </url>
@endforeach
</urlset>
