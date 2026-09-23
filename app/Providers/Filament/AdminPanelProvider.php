<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\InitialAvatarProvider;
use App\Filament\Pages\Auth\Login;
use App\Http\Middleware\AuthenticateAdminPanel;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login(Login::class)
            ->brandName(config('app.name'))
            ->brandLogo(fn () => view('filament.admin.logo'))
            // docs/DESIGN-TOKENS.md: الدرجة 600 هي --pri الفاتح و400 هي --pri الداكن، والبقية متدرّجة بينهما.
            // لوحة صريحة لأن توليد Filament من لون واحد يثبّت الإضاءة فيُفتّح الأخضر العميق.
            ->colors([
                'primary' => [
                    50 => '#EEF7F5',
                    100 => '#D5ECE7',
                    200 => '#ABD9CF',
                    300 => '#79C2B3',
                    400 => '#3FA593',
                    500 => '#16675D',
                    600 => '#0F4C45',
                    700 => '#0C3F39',
                    800 => '#0A332F',
                    900 => '#082925',
                    950 => '#051A18',
                ],
            ])
            ->darkMode()
            ->font('IBM Plex Sans Arabic', provider: LocalFontProvider::class)
            ->defaultAvatarProvider(InitialAvatarProvider::class)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            // دائمة: تُعاد مع طلبات Livewire، فيسري تعطيل الحساب أو تغيير الدور فورًا (docs/SPEC.md §2).
            ->authMiddleware([
                AuthenticateAdminPanel::class,
            ], isPersistent: true);
    }
}
