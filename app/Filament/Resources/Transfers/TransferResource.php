<?php

declare(strict_types=1);

namespace App\Filament\Resources\Transfers;

use App\Filament\Resources\Transfers\Pages\ListTransfers;
use App\Filament\Resources\Transfers\Tables\TransfersTable;
use App\Models\Transfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * حوالات اللوحة للمطابقة والإسناد والتعليق والمراجعة النهائية (FR-43..48).
 * الظهور بصلاحية transfers.view عبر TransferPolicy::viewAny.
 */
class TransferResource extends Resource
{
    protected static ?string $model = Transfer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getModelLabel(): string
    {
        return __('transfers.review.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('transfers.review.plural');
    }

    public static function table(Table $table): Table
    {
        return TransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTransfers::route('/'),
        ];
    }
}
