<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\RelationManagers;

use App\Actions\Content\RestorePageRevision;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;

/**
 * سجل نسخ الصفحة (docs/SPEC.md FR-52): للقراءة والاسترجاع فقط، بلا تعديل ولا حذف.
 * الاسترجاع ينشئ نسخة جديدة في آخر السجل بتأكيد قبل التنفيذ، ونسخة الأساس معلَّمة.
 */
class RevisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'revisions';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('pages.revisions.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return Gate::allows('restoreRevision', $ownerRecord);
    }

    #[On('refresh-revisions')]
    public function refreshRevisions(): void
    {
        $this->resetTable();
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label(__('pages.revisions.number'))
                    ->prefix('#'),
                TextColumn::make('created_at')
                    ->label(__('pages.revisions.created_at'))
                    ->dateTime('Y-m-d H:i', 'Asia/Riyadh'),
                TextColumn::make('author.full_name')
                    ->label(__('pages.revisions.author'))
                    ->placeholder(__('pages.revisions.system')),
                TextColumn::make('is_baseline')
                    ->label(__('pages.revisions.kind'))
                    ->badge()
                    ->state(fn (PageRevision $record): string => $record->is_baseline ? __('pages.revisions.baseline') : __('pages.revisions.regular'))
                    ->color(fn (PageRevision $record): string => $record->is_baseline ? 'warning' : 'gray'),
                TextColumn::make('blocks')
                    ->label(__('pages.revisions.blocks_count'))
                    ->state(fn (PageRevision $record): int => count($record->blocks)),
            ])
            ->recordActions([
                Action::make('restoreRevision')
                    ->label(__('pages.revisions.restore.label'))
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading(__('pages.revisions.restore.heading'))
                    ->modalDescription(__('pages.revisions.restore.description'))
                    ->modalSubmitActionLabel(__('pages.revisions.restore.submit'))
                    ->action(function (PageRevision $record): void {
                        /** @var Page $page */
                        $page = $this->getOwnerRecord();

                        app(RestorePageRevision::class)->handle($this->actor(), $page, $record);

                        $this->dispatch('page-revision-restored');

                        Notification::make()->success()->title(__('pages.revisions.restore.done'))->send();
                    }),
            ]);
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
