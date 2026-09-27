<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ Illuminate\Support\Facades\Lang::locale() === 'ar' ? 'rtl' : 'ltr' }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="color-scheme" content="light dark">

        @php
            $branding = \App\Models\SiteBranding::current();
            $pageTitle = $title ?? $branding->initiative_name;
            $pageDescription = $description ?? __('site.meta.description');
            $siteMenu = app(\App\Services\SiteMenu::class);
        @endphp
        <title>{{ $pageTitle }}</title>
        <meta name="description" content="{{ $pageDescription }}">
        @if ($noindex ?? false)
            <meta name="robots" content="noindex, nofollow">
        @endif
        <link rel="canonical" href="{{ url()->current() }}">
        <link rel="icon" type="image/svg+xml" href="{{ $branding->iconUrl() }}">
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="{{ $branding->initiative_name }}">
        <meta property="og:locale" content="ar_SA">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ url()->current() }}">
        <meta name="twitter:card" content="summary">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        {{-- ألوان الهوية (--pri, --brass) من site_branding، تُحقن بعد الأنماط الافتراضية لتجاوزها (tasks/T16-branding.md #4). --}}
        {!! \App\Services\BrandingCss::styleTag() !!}

        @livewireStyles
    </head>
    <body class="min-h-screen bg-bg text-ink antialiased flex flex-col safe-area-x">
        <header class="border-b border-line safe-area-top">
            <div class="mx-auto max-w-6xl px-4 py-2 flex flex-wrap items-center justify-between gap-x-4">
                <a href="{{ url('/') }}" class="flex min-h-11 items-center gap-2 whitespace-nowrap">
                    <img src="{{ $branding->lightLogoUrl() }}" alt="{{ $branding->initiative_name }}" class="h-8 w-auto dark:hidden" data-branding-logo="light">
                    <img src="{{ $branding->darkLogoUrl() }}" alt="{{ $branding->initiative_name }}" class="hidden h-8 w-auto dark:block" data-branding-logo="dark">
                </a>

                <nav aria-label="{{ __('pages.menus.locations.header') }}" class="flex flex-wrap items-center gap-x-1 whitespace-nowrap text-sm sm:gap-x-3">
                    {{-- عناصر القائمة من منشئ الصفحات (menu_items)، وروابط الحساب بعدها نظامية ثابتة. --}}
                    @foreach ($siteMenu->links(\App\MenuLocation::Header) as $link)
                        <a href="{{ $link['url'] }}" @if ($link['external']) rel="noopener noreferrer" @endif class="min-h-11 flex items-center px-2 text-ink hover:text-pri" data-menu="header">
                            {{ $link['label'] }}
                        </a>
                    @endforeach
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
            <div class="mx-auto max-w-6xl px-4 py-6 flex flex-col items-center gap-2 text-sm text-muted">
                @php($footerLinks = $siteMenu->links(\App\MenuLocation::Footer))
                @if ($footerLinks !== [])
                    <nav aria-label="{{ __('pages.menus.locations.footer') }}" class="mb-2 flex flex-wrap items-center justify-center gap-x-2">
                        @foreach ($footerLinks as $link)
                            <a href="{{ $link['url'] }}" @if ($link['external']) rel="noopener noreferrer" @endif class="min-h-11 flex items-center px-2 text-ink hover:text-pri" data-menu="footer">
                                {{ $link['label'] }}
                            </a>
                        @endforeach
                    </nav>
                @endif
                <img src="{{ $branding->lightLogoUrl() }}" alt="{{ $branding->initiative_name }}" class="h-6 w-auto dark:hidden" data-branding-logo="light">
                <img src="{{ $branding->darkLogoUrl() }}" alt="{{ $branding->initiative_name }}" class="hidden h-6 w-auto dark:block" data-branding-logo="dark">
                <p>&copy; {{ now()->year }} {{ $branding->initiative_name }}</p>
            </div>
        </footer>

        @livewireScripts
    </body>
</html>
