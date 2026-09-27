<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\ContactMessage;
use App\PermissionKey;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;

/**
 * صندوق رسائل "اتصل بنا" /admin/messages بصلاحية messages.view فقط (docs/SPEC.md
 * §9 contact_messages، FR-55). للقراءة والوسم "مقروءة" فقط، بلا تعديل ولا حذف.
 */
class Messages extends PermissionPage
{
    use WithPagination;

    public const int PER_PAGE = 15;

    protected static ?string $slug = 'messages';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;

    /** @var view-string */
    protected string $view = 'filament.pages.messages';

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::MessagesView;
    }

    public static function getNavigationLabel(): string
    {
        return __('contact.inbox.navigation');
    }

    public function getTitle(): string
    {
        return __('contact.inbox.title');
    }

    /**
     * @return LengthAwarePaginator<int, ContactMessage>
     */
    #[Computed]
    public function messages(): LengthAwarePaginator
    {
        return ContactMessage::query()->latest('id')->paginate(self::PER_PAGE);
    }

    public function markRead(int $id): void
    {
        $message = ContactMessage::query()->findOrFail($id);

        if (! $message->isRead()) {
            $message->forceFill(['read_at' => now()])->save();
        }

        unset($this->messages);

        Notification::make()->success()->title(__('contact.inbox.marked_read'))->send();
    }
}
