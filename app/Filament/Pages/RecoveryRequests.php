<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Recovery\CancelPasswordReset;
use App\Actions\Recovery\ChangeLoginPhone;
use App\Actions\Recovery\ClaimPasswordReset;
use App\Actions\Recovery\SendPasswordResetLink;
use App\Models\PasswordResetRequest;
use App\Models\User;
use App\PasswordResetStatus;
use App\PermissionKey;
use App\UserRole;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;

/**
 * طلبات الاستعادة: الاسم والجوال فقط، بلا بيانات حوالات (FR-9, FR-38).
 */
class RecoveryRequests extends PermissionPage
{
    protected static ?string $slug = 'recovery';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    /** @var view-string */
    protected string $view = 'filament.pages.recovery-requests';

    public ?int $sendingId = null;

    public string $destination = 'registered';

    public string $reason = '';

    public string $otherPhone = '';

    public ?int $changingId = null;

    public string $changeReason = '';

    public string $newPhone = '';

    public string $newPhoneConfirmation = '';

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::RecoveryHandle;
    }

    public static function getNavigationLabel(): string
    {
        return __('recovery.navigation');
    }

    public function getTitle(): string
    {
        return __('recovery.navigation');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = self::handleableRequests()->where('status', PasswordResetStatus::Pending)->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * @return Collection<int, PasswordResetRequest>
     */
    #[Computed]
    public function requests(): Collection
    {
        return self::handleableRequests()
            ->with('user:id,full_name,phone')
            ->whereIn('status', [
                PasswordResetStatus::Pending->value,
                PasswordResetStatus::Claimed->value,
                PasswordResetStatus::LinkSent->value,
            ])
            ->latest('id')
            ->get();
    }

    public function claim(int $id, ClaimPasswordReset $action): void
    {
        $this->act($id, fn (User $actor, PasswordResetRequest $request) => $action->handle($actor, $request));
    }

    public function cancel(int $id, CancelPasswordReset $action): void
    {
        $this->act($id, fn (User $actor, PasswordResetRequest $request) => $action->handle($actor, $request));
    }

    public function send(int $id, SendPasswordResetLink $action): void
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            return;
        }

        $request = PasswordResetRequest::query()->find($id);

        if ($request === null) {
            return;
        }

        Gate::authorize('handle', $request);

        try {
            $result = $action->handle(
                $actor,
                $request,
                $this->destination === 'other',
                $this->reason,
                $this->otherPhone,
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (AuthorizationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title(__('recovery.sent'))->success()->send();
        $this->redirect($result['whatsapp_url']);
    }

    public function changePhone(int $id, ChangeLoginPhone $action): void
    {
        $actor = auth()->user();
        $request = PasswordResetRequest::query()->find($id);

        if (! $actor instanceof User || $request === null) {
            return;
        }

        $action->handle($actor, $request, $this->changeReason, $this->newPhone, $this->newPhoneConfirmation);

        Notification::make()->title(__('recovery.phone_changed'))->success()->send();
        $this->reset('changingId', 'changeReason', 'newPhone', 'newPhoneConfirmation');
        unset($this->requests);
    }

    /**
     * @param  callable(User, PasswordResetRequest): void  $callback
     */
    private function act(int $id, callable $callback): void
    {
        $actor = auth()->user();
        $request = PasswordResetRequest::query()->find($id);

        if (! $actor instanceof User || $request === null) {
            return;
        }

        Gate::authorize('handle', $request);

        try {
            $callback($actor, $request);
        } catch (AuthorizationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();
        }

        unset($this->requests);
    }

    /**
     * طلبات حسابات المشرفين والمدير لا تظهر إلا للمدير (PasswordResetRequestPolicy).
     *
     * @return Builder<PasswordResetRequest>
     */
    private static function handleableRequests(): Builder
    {
        $query = PasswordResetRequest::query();
        $actor = auth()->user();

        if (! $actor instanceof User || $actor->role !== UserRole::Admin) {
            $query->whereHas('user', fn (Builder $owner): Builder => $owner->where('role', UserRole::User));
        }

        return $query;
    }
}
