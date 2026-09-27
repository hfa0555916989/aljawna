<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Actions\Content\CreatePage as CreatePageAction;
use App\Filament\Resources\Pages\PageResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreatePage extends CreateRecord
{
    protected static string $resource = PageResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            return app(CreatePageAction::class)->handle($actor, $data);
        } catch (ValidationException $exception) {
            throw PageResource::formValidationException($exception, $this->data ?? []);
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
