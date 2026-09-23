<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ Illuminate\Support\Facades\Lang::locale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light dark">

        <title>{{ $title ?? config('app.name') }}</title>

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="min-h-screen bg-bg text-ink antialiased flex flex-col safe-area-x">
        <header class="border-b border-line safe-area-top">
            <div class="mx-auto max-w-6xl px-4 py-3 flex items-center justify-between gap-4">
                <a href="{{ url('/') }}" class="flex items-center gap-2 font-brand text-xl font-bold text-pri">
                    {{ config('app.name') }}
                </a>

                <nav class="flex items-center gap-3 text-sm">
                    <a href="{{ url('/') }}" class="min-h-11 flex items-center px-2 text-ink hover:text-pri">
                        الرئيسية
                    </a>
                    @guest
                        <a href="{{ route('login') }}" class="min-h-11 flex items-center px-2 text-ink hover:text-pri">
                            الدخول
                        </a>
                        <a href="{{ route('register') }}" class="min-h-11 flex items-center px-2 text-ink hover:text-pri">
                            إنشاء حساب
                        </a>
                    @endguest
                    @auth
                        <a href="{{ route('dashboard') }}" class="min-h-11 flex items-center px-2 text-ink hover:text-pri">
                            لوحتي
                        </a>
                    @endauth
                </nav>
            </div>
        </header>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-line mt-8 safe-area-bottom">
            <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-muted text-center">
                &copy; {{ now()->year }} {{ config('app.name') }}
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
