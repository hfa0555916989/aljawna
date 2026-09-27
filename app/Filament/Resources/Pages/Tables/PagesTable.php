<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Tables;

use App\Models\Page;
use App\PageStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * قائمة الصفحات في اللوحة. لا حذف (PagePolicy)، والرئيسية معلَّمة "نظامية".
 */
class PagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->label(__('pages.fields.title'))
                    ->searchable()
                    ->wrap()
                    ->description(fn (Page $record): ?string => $record->is_system ? __('pages.system_page') : null),
                TextColumn::make('slug')
                    ->label(__('pages.fields.slug'))
                    ->state(fn (Page $record): string => $record->is_system ? '/' : '/'.$record->slug)
                    ->extraAttributes(['dir' => 'ltr']),
                TextColumn::make('status')
                    ->label(__('pages.fields.status'))
                    ->badge(),
                TextColumn::make('published_at')
                    ->label(__('pages.fields.published_at'))
                    ->dateTime('Y-m-d H:i', 'Asia/Riyadh')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('pages.fields.updated_at'))
                    ->dateTime('Y-m-d H:i', 'Asia/Riyadh')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('pages.fields.status'))
                    ->options(PageStatus::class),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
