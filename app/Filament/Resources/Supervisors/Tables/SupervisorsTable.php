<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supervisors\Tables;

use App\Actions\Supervisors\DeleteSupervisor;
use App\Actions\Supervisors\SetSupervisorActive;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;

class SupervisorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('full_name')
                    ->label(__('supervisors.name')),
                TextColumn::make('phone')
                    ->label(__('supervisors.phone'))
                    ->extraAttributes(['dir' => 'ltr']),
                IconColumn::make('is_active')
                    ->label(__('supervisors.active'))
                    ->boolean(),
                IconColumn::make('show_contact')
                    ->label(__('supervisors.show_contact'))
                    ->boolean(),
            ])
            ->emptyStateHeading(__('supervisors.empty'))
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label(__('supervisors.deactivate'))
                    ->visible(fn (User $record): bool => $record->is_active)
                    ->requiresConfirmation()
                    ->action(fn (User $record) => self::setActive($record, false)),
                Action::make('activate')
                    ->label(__('supervisors.activate'))
                    ->visible(fn (User $record): bool => ! $record->is_active)
                    ->action(fn (User $record) => self::setActive($record, true)),
                Action::make('deleteSupervisor')
                    ->label(__('supervisors.delete'))
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $actor = auth()->user();

                        if (! $actor instanceof User) {
                            return;
                        }

                        try {
                            app(DeleteSupervisor::class)->handle($actor, $record);
                        } catch (AuthorizationException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();

                            return;
                        } catch (QueryException) {
                            Notification::make()->title(__('supervisors.errors.delete_blocked'))->danger()->send();
                        }
                    }),
            ]);
    }

    private static function setActive(User $record, bool $active): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        try {
            app(SetSupervisorActive::class)->handle($actor, $record, $active);
        } catch (AuthorizationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }
    }
}
