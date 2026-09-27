<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SystemHealth as SystemHealthService;
use App\UserRole;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;

/**
 * صحة النظام للمدير فقط، للقراءة فقط (docs/DECISIONS.md): لا أزرار ولا أوامر
 * تُشغَّل على الخادم من الويب، ولا طرفية. يُعاد الفحص عند كل فتح أو تحديث للصفحة.
 *
 * @phpstan-import-type Check from SystemHealthService
 */
class SystemHealth extends Page
{
    protected static ?string $slug = 'system-health';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 100;

    /** @var view-string */
    protected string $view = 'filament.pages.system-health';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->is_active
            && $user->role === UserRole::Admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('health.navigation');
    }

    public function getTitle(): string
    {
        return __('health.navigation');
    }

    /**
     * @return list<Check>
     */
    #[Computed]
    public function checks(): array
    {
        return app(SystemHealthService::class)->checks();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function versions(): array
    {
        return app(SystemHealthService::class)->versions();
    }
}
