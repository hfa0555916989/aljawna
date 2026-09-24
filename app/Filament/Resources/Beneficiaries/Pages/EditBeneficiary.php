<?php

declare(strict_types=1);

namespace App\Filament\Resources\Beneficiaries\Pages;

use App\Actions\Beneficiaries\ApproveBeneficiary;
use App\Actions\Beneficiaries\ChangeBeneficiaryStatus;
use App\Actions\Beneficiaries\UpdateBeneficiary;
use App\BeneficiaryStatus;
use App\Exceptions\BankAccountChangeNotConfirmed;
use App\Filament\Resources\Beneficiaries\BeneficiaryResource;
use App\Models\Beneficiary;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

/**
 * تعديل المستفيد. تغيير أي حقل بنكي يوقف الحفظ ويفتح نافذة تأكيد صريحة،
 * ولا يُحفظ إلا من زر التأكيد فيها (docs/SPEC.md FR-33, §12.10).
 *
 * @property-read Beneficiary $record
 */
class EditBeneficiary extends EditRecord
{
    protected static string $resource = BeneficiaryResource::class;

    /**
     * يُضبط داخل إجراء التأكيد فقط في الطلب نفسه، وليس حالة Livewire يرسلها المتصفح.
     */
    protected bool $bankChangeConfirmed = false;

    public function getSubheading(): string
    {
        return $this->record->status->getLabel().' · '.BeneficiaryResource::approvalLabel($this->record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Beneficiary $record */
        try {
            return app(UpdateBeneficiary::class)->handle($this->actor(), $record, $data, $this->bankChangeConfirmed);
        } catch (BankAccountChangeNotConfirmed) {
            $this->mountAction('confirmBankChange');

            throw new Halt;
        } catch (ValidationException $exception) {
            throw BeneficiaryResource::formValidationException($exception);
        }
    }

    public function confirmBankChangeAction(): Action
    {
        return Action::make('confirmBankChange')
            ->requiresConfirmation()
            ->color('danger')
            ->modalHeading(__('beneficiaries.bank_change.heading'))
            ->modalDescription(fn (): HtmlString => $this->pendingBankChangesDescription())
            ->modalSubmitActionLabel(__('beneficiaries.bank_change.submit'))
            ->action(function (): void {
                $this->bankChangeConfirmed = true;

                $this->save();
            });
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label(__('beneficiaries.actions.approve.label'))
                ->color('success')
                ->visible(fn (): bool => ! $this->record->isApproved() && $this->actor()->can('approve', $this->record))
                ->requiresConfirmation()
                ->modalHeading(__('beneficiaries.actions.approve.heading'))
                ->modalDescription(__('beneficiaries.actions.approve.description'))
                ->modalSubmitActionLabel(__('beneficiaries.actions.approve.submit'))
                ->action(function (): void {
                    app(ApproveBeneficiary::class)->handle($this->actor(), $this->record);

                    Notification::make()->success()->title(__('beneficiaries.actions.approve.done'))->send();
                }),
            Action::make('close')
                ->label(__('beneficiaries.actions.close.label'))
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === BeneficiaryStatus::Active)
                ->requiresConfirmation()
                ->modalHeading(__('beneficiaries.actions.close.heading'))
                ->modalDescription(__('beneficiaries.actions.close.description'))
                ->modalSubmitActionLabel(__('beneficiaries.actions.close.submit'))
                ->action(fn () => $this->changeStatus(BeneficiaryStatus::Closed, 'close')),
            Action::make('reopen')
                ->label(__('beneficiaries.actions.reopen.label'))
                ->color('gray')
                ->visible(fn (): bool => $this->record->status === BeneficiaryStatus::Closed)
                ->requiresConfirmation()
                ->modalHeading(__('beneficiaries.actions.reopen.heading'))
                ->modalDescription(__('beneficiaries.actions.reopen.description'))
                ->modalSubmitActionLabel(__('beneficiaries.actions.reopen.submit'))
                ->action(fn () => $this->changeStatus(BeneficiaryStatus::Active, 'reopen')),
        ];
    }

    private function changeStatus(BeneficiaryStatus $status, string $action): void
    {
        app(ChangeBeneficiaryStatus::class)->handle($this->actor(), $this->record, $status);

        Notification::make()->success()->title(__("beneficiaries.actions.{$action}.done"))->send();
    }

    private function pendingBankChangesDescription(): HtmlString
    {
        /** @var array<string, mixed> $state */
        $state = $this->data ?? [];
        $lines = [];

        foreach (UpdateBeneficiary::pendingBankChanges($this->record, $state) as $field => $change) {
            $lines[] = '<li>'.e(__('beneficiaries.bank_change.change_line', [
                'field' => __('beneficiaries.fields.'.$field),
                'old' => $change['old'],
                'new' => $change['new'],
            ])).'</li>';
        }

        return new HtmlString(
            '<p>'.e(__('beneficiaries.bank_change.description')).'</p>'
            .'<ul class="mt-2 list-disc ps-5 text-start" dir="rtl">'.implode('', $lines).'</ul>'
        );
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
