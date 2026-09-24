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

        @can('create', \App\Models\Transfer::class)
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('transfers.create') }}" class="inline-flex min-h-11 items-center rounded-[10px] bg-pri px-4 text-sm font-semibold text-pri-ink hover:opacity-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">
                    {{ __('transfers.dashboard.upload') }}
                </a>
                <a href="{{ route('transfers.index') }}" class="inline-flex min-h-11 items-center rounded-[10px] border border-pri px-4 text-sm font-semibold text-pri hover:bg-brass-soft focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-pri">
                    {{ __('transfers.dashboard.mine') }}
                </a>
            </div>
        @endcan
    </div>
</x-layouts::app>
