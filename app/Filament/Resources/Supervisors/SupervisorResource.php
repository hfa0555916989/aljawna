<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supervisors;

use App\Filament\Resources\Supervisors\Pages\EditSupervisor;
use App\Filament\Resources\Supervisors\Pages\ListSupervisors;
use App\Filament\Resources\Supervisors\Schemas\SupervisorForm;
use App\Filament\Resources\Supervisors\Tables\SupervisorsTable;
use App\Models\User;
use App\PermissionKey;
use App\UserRole;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * دعوة المشرفين وإدارتهم (docs/SPEC.md FR-19..21). الظهور بصلاحية supervisors.manage.
 */
class SupervisorResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?string $recordTitleAttribute = 'full_name';

    public static function getModelLabel(): string
    {
        return __('supervisors.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('supervisors.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('supervisors.navigation');
    }

    public static function form(Schema $schema): Schema
    {
        return SupervisorForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupervisorsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('role', UserRole::Supervisor);
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->can(PermissionKey::SupervisorsManage->value);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return self::canViewAny();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupervisors::route('/'),
            'edit' => EditSupervisor::route('/{record}/edit'),
        ];
    }
}
