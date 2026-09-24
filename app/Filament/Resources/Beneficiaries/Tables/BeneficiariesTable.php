<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries\Tables;

use App\BeneficiaryStatus;
use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Models\Beneficiary;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * قائمة المستفيدين في اللوحة. الاسم يُعرض كاملًا ويلتف (FR-36).
 */
class BeneficiariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('display_name')
                    ->label(__('beneficiaries.fields.display_name'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('bank_name')
                    ->label(__('beneficiaries.fields.bank_name'))
                    ->wrap(),
                TextColumn::make('target_amount')
                    ->label(__('beneficiaries.fields.target_amount'))
                    ->numeric(decimalPlaces: 2, locale: 'en')
                    ->suffix(' '.__('beneficiaries.currency'))
                    ->sortable(),
                TextColumn::make('target_deadline')
                    ->label(__('beneficiaries.fields.target_deadline'))
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('beneficiaries.fields.status'))
                    ->badge(),
                TextColumn::make('approved_at')
                    ->label(__('beneficiaries.fields.approval'))
                    ->badge()
                    ->state(fn (Beneficiary $record): string => BeneficiaryResource::approvalLabel($record))
                    ->color(fn (Beneficiary $record): string => BeneficiaryResource::approvalColor($record)),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('beneficiaries.fields.status'))
                    ->options(BeneficiaryStatus::class),
                TernaryFilter::make('approved')
                    ->label(__('beneficiaries.fields.approval'))
                    ->trueLabel(__('beneficiaries.approval.approved'))
                    ->falseLabel(__('beneficiaries.approval.pending'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('approved_at'),
                        false: fn (Builder $query): Builder => $query->whereNull('approved_at'),
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
