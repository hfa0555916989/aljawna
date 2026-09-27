<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Actions\Content\RestoreBaseDesign;
use App\Filament\Pages\Menus;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use App\Models\User;
use App\Services\BaseDesign;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

/**
 * قائمة الصفحات، ومنها زر "استعادة التصميم الأساسي" بتأكيد قبل التنفيذ (قرار المالك
 * في docs/DECISIONS.md). الرئيسية تُنشأ بالتصميم الأساسي عند فتح القائمة أول مرة.
 */
class ListPages extends ListRecords
{
    protected static string $resource = PageResource::class;

    public function mount(): void
    {
        app(BaseDesign::class)->home();

        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('menus')
                ->label(__('pages.menus.navigation'))
                ->icon(Heroicon::OutlinedBars3)
                ->color('gray')
                ->url(fn (): string => Menus::getUrl()),
            Action::make('restoreBaseDesign')
                ->label(__('pages.base.restore.label'))
                ->icon(Heroicon::OutlinedArrowUturnLeft)
                ->color('danger')
                ->visible(fn (): bool => $this->actor()->can('restoreBaseDesign', Page::class))
                ->requiresConfirmation()
                ->modalHeading(__('pages.base.restore.heading'))
                ->modalDescription(__('pages.base.restore.description'))
                ->modalSubmitActionLabel(__('pages.base.restore.submit'))
                ->action(function (): void {
                    app(RestoreBaseDesign::class)->handle($this->actor());

                    Notification::make()->success()->title(__('pages.base.restore.done'))->send();
                }),
        ];
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
