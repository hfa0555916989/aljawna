<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pages\Pages;

use App\Actions\Content\PublishPage;
use App\Actions\Content\SavePageDraft;
use App\Actions\Content\UnpublishPage;
use App\Filament\Resources\Pages\PageResource;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

/**
 * تحرير مسودة الصفحة (docs/SPEC.md FR-52): الحفظ لا يغيّر ما يراه الزوار، والمعاينة
 * تعرض آخر مسودة محفوظة دون نشر، والنشر يحفظ المسودة ثم يكتب نسخة جديدة منشورة.
 *
 * @property-read Page $record
 */
class EditPage extends EditRecord
{
    protected static string $resource = PageResource::class;

    public function getSubheading(): string
    {
        return $this->record->status->getLabel().($this->record->is_system ? ' · '.__('pages.system_page') : '');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        try {
            return app(SavePageDraft::class)->handle($this->actor(), $record, $data);
        } catch (ValidationException $exception) {
            throw PageResource::formValidationException($exception, $this->data ?? []);
        }
    }

    /**
     * بعد استرجاع نسخة من السجل تتغيّر المسودة، فيُعاد ملء النموذج بها.
     */
    #[On('page-revision-restored')]
    public function reloadDraft(): void
    {
        $this->record->refresh();
        $this->fillForm();
    }

    protected function getSavedNotificationTitle(): string
    {
        return __('pages.actions.save.done');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('pages.actions.preview.label'))
                ->tooltip(__('pages.actions.preview.hint'))
                ->icon(Heroicon::OutlinedEye)
                ->color('gray')
                ->url(fn (): string => route('admin.pages.preview', $this->record))
                ->openUrlInNewTab(),
            Action::make('publish')
                ->label(__('pages.actions.publish.label'))
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading(__('pages.actions.publish.heading'))
                ->modalDescription(__('pages.actions.publish.description'))
                ->modalSubmitActionLabel(__('pages.actions.publish.submit'))
                ->action(function (): void {
                    $this->save(shouldRedirect: false, shouldSendSavedNotification: false);

                    app(PublishPage::class)->handle($this->actor(), $this->record);

                    $this->dispatch('refresh-revisions');

                    Notification::make()->success()->title(__('pages.actions.publish.done'))->send();
                }),
            Action::make('unpublish')
                ->label(__('pages.actions.unpublish.label'))
                ->icon(Heroicon::OutlinedEyeSlash)
                ->color('danger')
                ->visible(fn (): bool => $this->actor()->can('unpublish', $this->record))
                ->requiresConfirmation()
                ->modalHeading(__('pages.actions.unpublish.heading'))
                ->modalDescription(__('pages.actions.unpublish.description'))
                ->modalSubmitActionLabel(__('pages.actions.unpublish.submit'))
                ->action(function (): void {
                    app(UnpublishPage::class)->handle($this->actor(), $this->record);

                    Notification::make()->success()->title(__('pages.actions.unpublish.done'))->send();
                }),
        ];
    }

    private function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
