<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Actions\Users\SetInitiatorActive;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use App\PermissionKey;
use App\Services\SuspiciousAccounts;
use App\Support\HijriDate;
use App\UserRole;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Throwable;

/**
 * تبويب الأمان: الحسابات المشبوهة وتعطيلها، وسجل المحاولات، وسجل التدقيق بمرشحات
 * (docs/SPEC.md §12.7, FR-22, FR-23). الصفحة لـ security.view، والتعطيل لـ users.suspend.
 */
class Security extends PermissionPage
{
    use WithPagination;

    public const int PER_PAGE = 15;

    public const string ACTOR_SYSTEM = 'system';

    /** @var list<string> */
    public const array TABS = ['suspicious', 'attempts', 'audit'];

    protected static ?string $slug = 'security';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    /** @var view-string */
    protected string $view = 'filament.pages.security';

    public string $tab = 'suspicious';

    public string $auditAction = '';

    public string $auditActor = '';

    public string $auditFrom = '';

    public string $auditTo = '';

    public ?int $togglingId = null;

    public string $toggleReason = '';

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::SecurityView;
    }

    public static function getNavigationLabel(): string
    {
        return __('security.navigation');
    }

    public function getTitle(): string
    {
        return __('security.navigation');
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, self::TABS, true)) {
            $this->tab = $tab;
        }
    }

    public function updated(string $property): void
    {
        if (str_starts_with($property, 'audit')) {
            $this->resetPage('audit');
        }
    }

    /**
     * @return list<array{user: User, flags: list<array{rule: string, count: int}>}>
     */
    #[Computed]
    public function suspicious(): array
    {
        return app(SuspiciousAccounts::class)->detect();
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function suspended(): LengthAwarePaginator
    {
        return User::query()
            ->select(['id', 'full_name', 'phone', 'is_active', 'role'])
            ->where('role', UserRole::User)
            ->where('is_active', false)
            ->latest('id')
            ->paginate(self::PER_PAGE, pageName: 'suspended');
    }

    /**
     * @return LengthAwarePaginator<int, LoginAttempt>
     */
    #[Computed]
    public function attempts(): LengthAwarePaginator
    {
        return LoginAttempt::query()
            ->latest('id')
            ->paginate(self::PER_PAGE, pageName: 'attempts');
    }

    /**
     * @return LengthAwarePaginator<int, AuditLog>
     */
    #[Computed]
    public function auditEntries(): LengthAwarePaginator
    {
        $from = $this->parseDay($this->auditFrom);
        $toExclusive = $this->parseDay($this->auditTo)?->addDay();

        return AuditLog::query()
            ->with('actor:id,full_name')
            ->when($this->auditAction !== '', fn (Builder $query): Builder => $query->where('action', $this->auditAction))
            ->when($this->auditActor === self::ACTOR_SYSTEM, fn (Builder $query): Builder => $query->whereNull('actor_id'))
            ->when(
                ctype_digit($this->auditActor),
                fn (Builder $query): Builder => $query->where('actor_id', (int) $this->auditActor),
            )
            ->when($from !== null, fn (Builder $query): Builder => $query->where('created_at', '>=', $from))
            ->when($toExclusive !== null, fn (Builder $query): Builder => $query->where('created_at', '<', $toExclusive))
            ->latest('id')
            ->paginate(self::PER_PAGE, pageName: 'audit');
    }

    /**
     * @return list<string>
     */
    #[Computed]
    public function auditActions(): array
    {
        /** @var list<string> */
        return AuditLog::query()->distinct()->orderBy('action')->pluck('action')->all();
    }

    /**
     * @return Collection<int, User>
     */
    #[Computed]
    public function auditActors(): Collection
    {
        return User::query()
            ->whereIn('id', AuditLog::query()->whereNotNull('actor_id')->select('actor_id'))
            ->orderBy('full_name')
            ->get(['id', 'full_name']);
    }

    public function startToggle(int $userId): void
    {
        $this->togglingId = $userId;
        $this->toggleReason = '';
        $this->resetErrorBag();
    }

    public function cancelToggle(): void
    {
        $this->reset('togglingId', 'toggleReason');
        $this->resetErrorBag();
    }

    public function toggleActive(int $userId, SetInitiatorActive $action): void
    {
        Gate::authorize(PermissionKey::UsersSuspend->value);

        $actor = auth()->user();
        $user = User::query()->find($userId);

        if (! $actor instanceof User || $user === null) {
            return;
        }

        try {
            $action->handle($actor, $user, ! $user->is_active, $this->toggleReason);
        } catch (AuthorizationException $exception) {
            Notification::make()->title($exception->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title(__($user->is_active ? 'security.activated_done' : 'security.suspended_done'))
            ->success()
            ->send();

        $this->reset('togglingId', 'toggleReason');
        unset($this->suspicious, $this->suspended);
    }

    private function parseDay(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            $day = CarbonImmutable::createFromFormat('Y-m-d', $value, HijriDate::TIMEZONE);
        } catch (Throwable) {
            return null;
        }

        return $day instanceof CarbonImmutable ? $day->startOfDay() : null;
    }
}
