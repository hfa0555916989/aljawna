<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supervisors\Pages;

use App\Actions\Supervisors\UpdateSupervisorContact;
use App\Actions\Supervisors\UpdateSupervisorPermissions;
use App\Filament\Resources\Supervisors\SupervisorResource;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @property-read User $record
 */
class EditSupervisor extends EditRecord
{
    protected static string $resource = SupervisorResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['permissions'] = $this->record->permissions()->pluck('name')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return $record;
        }

        /** @var list<string> $permissions */
        $permissions = array_values($data['permissions'] ?? []);

        try {
            app(UpdateSupervisorPermissions::class)->handle($actor, $record, $permissions);
            app(UpdateSupervisorContact::class)->handle($actor, $record, (bool) ($data['show_contact'] ?? false));
        } catch (AuthorizationException|ValidationException $exception) {
            $message = $exception instanceof ValidationException
                ? (string) collect($exception->errors())->flatten()->first()
                : $exception->getMessage();

            throw ValidationException::withMessages(['permissions' => $message]);
        }

        return $record->fresh() ?? $record;
    }
}
