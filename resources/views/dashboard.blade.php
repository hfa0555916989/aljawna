<x-layouts::app title="لوحتي">
    <div class="mx-auto max-w-6xl px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-2xl font-bold text-ink">لوحتي</h1>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"
                        class="min-h-11 rounded-[10px] border border-line bg-surface px-4 text-sm font-medium text-ink hover:border-pri focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">
                    تسجيل الخروج
                </button>
            </form>
        </div>

        <p class="mt-2 break-words text-muted">مرحبًا، {{ auth()->user()?->full_name }}</p>
    </div>
</x-layouts::app>
