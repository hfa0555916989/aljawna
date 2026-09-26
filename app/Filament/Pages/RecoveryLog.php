<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\RecoveryLog as RecoveryLogEntry;
use App\Models\User;
use App\UserRole;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;

/**
 * سجل كلمات المرور المستعادة للمدير فقط، للقراءة (docs/SPEC.md §4.3, FR-41).
 */
class RecoveryLog extends Page
{
    protected static ?string $slug = 'recovery/log';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    /** @var view-string */
    protected string $view = 'filament.pages.recovery-log';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->is_active
            && $user->role === UserRole::Admin;
    }

    public static function getNavigationLabel(): string
    {
        return __('recovery.log_navigation');
    }

    public function getTitle(): string
    {
        return __('recovery.log_navigation');
    }

    /**
     * @return Collection<int, RecoveryLogEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        return RecoveryLogEntry::query()
            ->with([
                'user:id,full_name,phone',
                'performer:id,full_name',
            ])
            ->latest('id')
            ->get();
    }
}
