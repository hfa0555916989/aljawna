<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supervisors\Schemas;

use App\PermissionKey;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SupervisorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            CheckboxList::make('permissions')
                ->label(__('supervisors.permissions'))
                ->options(self::permissionOptions())
                ->columns(1)
                ->bulkToggleable(),
            Toggle::make('show_contact')
                ->label(__('supervisors.show_contact')),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function permissionOptions(): array
    {
        $options = [];

        foreach (PermissionKey::cases() as $permission) {
            $options[$permission->value] = __('supervisors.permission_labels.'.str_replace('.', '_', $permission->value));
        }

        return $options;
    }
}
