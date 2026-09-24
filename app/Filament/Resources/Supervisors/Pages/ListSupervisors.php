<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supervisors\Pages;

use App\Actions\Supervisors\InviteSupervisor;
use App\Filament\Resources\Supervisors\Schemas\SupervisorForm;
use App\Filament\Resources\Supervisors\SupervisorResource;
use App\Models\User;
use App\Support\PermissionTemplates;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ListSupervisors extends ListRecords
{
    protected static string $resource = SupervisorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('invite')
                ->label(__('supervisors.invite.action'))
                ->schema([
                    TextInput::make('phone')
                        ->label(__('supervisors.invite.phone'))
                        ->tel()
                        ->required()
                        ->extraInputAttributes(['dir' => 'ltr']),
                    Select::make('template')
                        ->label(__('supervisors.invite.template'))
                        ->options(collect(PermissionTemplates::all())->mapWithKeys(
                            fn (array $template, string $key): array => [$key => $template['label']],
                        )->all())
                        ->live()
                        ->afterStateUpdated(function (?string $state, Set $set): void {
                            if ($state === null || $state === '') {
                                return;
                            }

                            $set('permissions', PermissionTemplates::permissions($state));
                        }),
                    CheckboxList::make('permissions')
                        ->label(__('supervisors.permissions'))
                        ->options(SupervisorForm::permissionOptions())
                        ->columns(1),
                ])
                ->action(function (array $data): void {
                    $actor = auth()->user();

                    if (! $actor instanceof User) {
                        return;
                    }

                    try {
                        $result = app(InviteSupervisor::class)->handle(
                            $actor,
                            (string) $data['phone'],
                            array_values($data['permissions'] ?? []),
                            isset($data['template']) ? (string) $data['template'] : null,
                        );
                    } catch (ValidationException $exception) {
                        $message = collect($exception->errors())->flatten()->first();

                        Notification::make()
                            ->title(is_string($message) ? $message : __('supervisors.errors.phone'))
                            ->danger()
                            ->send();

                        return;
                    } catch (AuthorizationException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title(__('supervisors.invite.ready'))
                        ->success()
                        ->send();

                    $this->redirect($result['whatsapp_url']);
                }),
        ];
    }
}
