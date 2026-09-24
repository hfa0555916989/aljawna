<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries\Schemas;

use App\Models\Beneficiary;
use App\Rules\BankAccountNumber as BankAccountNumberRule;
use App\Rules\NoBankAccountInText;
use App\Rules\NotInPast;
use App\Rules\SaudiIban as SaudiIbanRule;
use App\Rules\UniqueBeneficiaryIban;
use App\Support\BankAccountNumber;
use App\Support\HijriDate;
use App\Support\SaudiBanks;
use App\Support\SaudiIban;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * نموذج تسجيل المستفيد وتعديله بالحقول الخمسة من FR-29 بأسمائها، ثم المبلغ والمواعيد.
 */
class BeneficiaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make(__('beneficiaries.sections.account'))
                    ->description(__('beneficiaries.sections.account_description'))
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('display_name')
                            ->label(__('beneficiaries.fields.display_name'))
                            ->required()
                            ->maxLength(255)
                            ->rule(new NoBankAccountInText),
                        TextInput::make('account_holder')
                            ->label(__('beneficiaries.fields.account_holder'))
                            ->required()
                            ->maxLength(255)
                            ->rule(new NoBankAccountInText),
                        TextInput::make('bank_name')
                            ->label(__('beneficiaries.fields.bank_name'))
                            ->required()
                            ->maxLength(255)
                            ->datalist(SaudiBanks::names())
                            ->live(onBlur: true)
                            ->rule(new NoBankAccountInText),
                        TextInput::make('account_number')
                            ->label(__('beneficiaries.fields.account_number'))
                            ->required()
                            ->maxLength(BankAccountNumber::MAX_LENGTH + 10)
                            ->rule(new BankAccountNumberRule)
                            ->dehydrateStateUsing(fn (?string $state): string => BankAccountNumber::normalize($state))
                            ->extraInputAttributes(['dir' => 'ltr', 'inputmode' => 'numeric', 'autocomplete' => 'off']),
                        TextInput::make('iban')
                            ->label(__('beneficiaries.fields.iban'))
                            ->required()
                            ->maxLength(SaudiIban::LENGTH + 10)
                            ->placeholder('SA0000000000000000000000')
                            ->rule(new SaudiIbanRule)
                            ->rule(fn (?Beneficiary $record): UniqueBeneficiaryIban => new UniqueBeneficiaryIban($record))
                            ->live(onBlur: true)
                            ->dehydrateStateUsing(fn (?string $state): string => SaudiIban::normalize($state))
                            ->hint(fn (Get $get, ?string $state): ?string => self::bankMismatchHint($get('bank_name'), $state))
                            ->hintColor('warning')
                            ->hintIcon(fn (Get $get, ?string $state): ?Heroicon => self::bankMismatchHint($get('bank_name'), $state) === null ? null : Heroicon::OutlinedExclamationTriangle)
                            ->extraInputAttributes(['dir' => 'ltr', 'autocomplete' => 'off', 'class' => 'font-mono'])
                            ->columnSpanFull(),
                    ]),
                Section::make(__('beneficiaries.sections.target'))
                    ->columns(['default' => 1, 'lg' => 2])
                    ->schema([
                        TextInput::make('target_amount')
                            ->label(__('beneficiaries.fields.target_amount'))
                            ->required()
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(9999999999.99)
                            ->step(0.01)
                            ->suffix(__('beneficiaries.currency'))
                            ->extraInputAttributes(['dir' => 'ltr'])
                            ->columnSpanFull(),
                        self::dateField('target_deadline'),
                        self::dateField('recommended_deadline')
                            ->afterOrEqual('target_deadline')
                            ->validationMessages(['after_or_equal' => __('beneficiaries.validation.recommended_before_target')]),
                        self::dateField('wedding_date')
                            ->afterOrEqual('recommended_deadline')
                            ->validationMessages(['after_or_equal' => __('beneficiaries.validation.wedding_before_recommended')]),
                    ]),
            ]);
    }

    /**
     * حقل تاريخ ميلادي مع معاينة هجرية (أم القرى) تحته. لا يُقبل تاريخ ماضٍ عند التسجيل فقط،
     * لأن مواعيد المستفيد المسجَّل قد تمضي ويبقى تعديل بقية بياناته ممكنًا.
     */
    private static function dateField(string $name): DatePicker
    {
        return DatePicker::make($name)
            ->label(__('beneficiaries.fields.'.$name))
            ->required()
            ->rule(new NotInPast, fn (string $operation): bool => $operation === 'create')
            ->live()
            ->helperText(function (?string $state): ?string {
                $hijri = HijriDate::format($state);
                $hint = $hijri === null ? null : __('beneficiaries.hints.hijri', ['date' => $hijri]);

                return is_string($hint) ? $hint : null;
            });
    }

    private static function bankMismatchHint(mixed $bankName, ?string $iban): ?string
    {
        $expectedBank = SaudiBanks::mismatchFor(is_string($bankName) ? $bankName : null, $iban);

        $hint = $expectedBank === null ? null : __('beneficiaries.hints.bank_mismatch', ['bank' => $expectedBank]);

        return is_string($hint) ? $hint : null;
    }
}
