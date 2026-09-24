<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Actions\Beneficiaries\UpdateBeneficiary;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * تنبيه ظاهر للمدير بكل تغيير حديث لحساب بنكي لمستفيد (docs/SPEC.md §12.10).
 */
class BankAccountChangesAlert extends Widget
{
    protected static ?int $sort = -3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.bank-account-changes-alert';

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isActiveAdmin() && static::recentChangesQuery()->exists();
    }

    /**
     * @return array{changes: Collection<int, AuditLog>, days: int}
     */
    protected function getViewData(): array
    {
        return [
            'changes' => static::recentChangesQuery()->with(['actor', 'subject'])->latest('created_at')->latest('id')->get(),
            'days' => static::alertDays(),
        ];
    }

    /**
     * @return Builder<AuditLog>
     */
    protected static function recentChangesQuery(): Builder
    {
        return AuditLog::query()
            ->where('action', UpdateBeneficiary::AUDIT_ACTION)
            ->where('subject_type', (new Beneficiary)->getMorphClass())
            ->where('created_at', '>=', now()->subDays(static::alertDays()));
    }

    protected static function alertDays(): int
    {
        return max(1, (int) config('security.bank_account_changes.alert_days'));
    }
}
