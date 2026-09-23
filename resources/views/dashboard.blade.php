<x-layouts::app title="لوحتي">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <h1 class="text-2xl font-bold text-ink">لوحتي</h1>
        <p class="mt-2 break-words text-muted">مرحبًا، {{ auth()->user()?->full_name }}</p>
    </div>
</x-layouts::app>
