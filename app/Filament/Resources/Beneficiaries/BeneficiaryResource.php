<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries;

use App\Filament\Resources\Beneficiaries\Pages\CreateBeneficiary;
use App\Filament\Resources\Beneficiaries\Pages\EditBeneficiary;
use App\Filament\Resources\Beneficiaries\Pages\ListBeneficiaries;
use App\Filament\Resources\Beneficiaries\Schemas\BeneficiaryForm;
use App\Filament\Resources\Beneficiaries\Tables\BeneficiariesTable;
use App\Models\Beneficiary;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

/**
 * إدارة المستفيدين في اللوحة (docs/SPEC.md FR-29..33). الوصول عبر BeneficiaryPolicy
 * بصلاحية beneficiaries.manage، ولا حذف.
 */
class BeneficiaryResource extends Resource
{
    protected static ?string $model = Beneficiary::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'display_name';

    public static function getModelLabel(): string
    {
        return __('beneficiaries.model');
    }

    public static function getPluralModelLabel(): string
    {
        return __('beneficiaries.plural');
    }

    public static function form(Schema $schema): Schema
    {
        return BeneficiaryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BeneficiariesTable::configure($table);
    }

    public static function approvalLabel(Beneficiary $beneficiary): string
    {
        $label = __(match (true) {
            $beneficiary->isApproved() => 'beneficiaries.approval.approved',
            $beneficiary->awaitsAdminReapproval() => 'beneficiaries.approval.awaiting_admin',
            default => 'beneficiaries.approval.pending',
        });

        return is_string($label) ? $label : '';
    }

    public static function approvalColor(Beneficiary $beneficiary): string
    {
        return match (true) {
            $beneficiary->isApproved() => 'success',
            $beneficiary->awaitsAdminReapproval() => 'danger',
            default => 'warning',
        };
    }

    /**
     * أخطاء التحقق من الإجراءات تأتي بأسماء الحقول، والنموذج يعرضها تحت data.*.
     */
    public static function formValidationException(ValidationException $exception): ValidationException
    {
        $messages = [];

        foreach ($exception->errors() as $field => $fieldMessages) {
            $messages['data.'.$field] = $fieldMessages;
        }

        return ValidationException::withMessages($messages);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBeneficiaries::route('/'),
            'create' => CreateBeneficiary::route('/create'),
            'edit' => EditBeneficiary::route('/{record}/edit'),
        ];
    }
}
