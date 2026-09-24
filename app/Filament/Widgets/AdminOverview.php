<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\PermissionKey;
use App\Services\StatsService;
use App\Support\Money;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Gate;

/**
 * "نظرة عامة" في لوحة الإدارة: مؤشرات مجموع المبادرة (docs/SPEC.md §7, FR-31، stats.view).
 */
class AdminOverview extends StatsOverviewWidget
{
    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    public static function canView(): bool
    {
        return Gate::allows(PermissionKey::StatsView->value);
    }

    protected function getHeading(): ?string
    {
        return __('admin.dashboard.overview.heading');
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $service = app(StatsService::class);
        $counts = $service->beneficiaryCounts();
        $overall = $service->forBeneficiary();

        return [
            Stat::make(__('dashboard.cards.available'), (string) $counts['available']),
            Stat::make(__('dashboard.cards.closed'), (string) $counts['closed']),
            Stat::make(__('dashboard.cards.initiators'), (string) $overall['initiators_count']),
            Stat::make(__('dashboard.cards.total'), Money::format($overall['total'])),
            Stat::make(__('dashboard.cards.receipts'), (string) $overall['receipts_count']),
            Stat::make(__('dashboard.cards.average'), Money::format($overall['average'])),
            Stat::make(__('dashboard.cards.remaining'), $overall['remaining_percentage'].'%'),
        ];
    }
}
