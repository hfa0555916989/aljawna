<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\PermissionKey;
use App\Services\RecoveryStatsService;
use App\Support\HijriDate;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Computed;
use Throwable;

/**
 * إحصائيات الاستعادة بالفترات، للمدير ولمن يملك stats.recovery (docs/SPEC.md §4.4, FR-42).
 */
class RecoveryStats extends PermissionPage
{
    protected static ?string $slug = 'recovery/stats';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    /** @var view-string */
    protected string $view = 'filament.pages.recovery-stats';

    public string $period = RecoveryStatsService::PERIOD_DAY;

    public string $date = '';

    public string $month = '';

    public string $year = '';

    public string $startDate = '';

    public string $endDate = '';

    public function mount(): void
    {
        $today = CarbonImmutable::now(HijriDate::TIMEZONE);

        $this->date = $today->toDateString();
        $this->month = $today->format('Y-m');
        $this->year = (string) $today->year;
        $this->startDate = $today->toDateString();
        $this->endDate = $today->toDateString();
    }

    public static function getRequiredPermission(): PermissionKey
    {
        return PermissionKey::StatsRecovery;
    }

    public static function getNavigationLabel(): string
    {
        return __('recovery.stats.navigation');
    }

    public function getTitle(): string
    {
        return __('recovery.stats.navigation');
    }

    /**
     * حدود الفترة المختارة [البداية، النهاية) بتوقيت الرياض (docs/SPEC.md §4.4).
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    #[Computed]
    public function range(): array
    {
        return app(RecoveryStatsService::class)->boundsFor(
            $this->period,
            day: $this->parseDate($this->date),
            month: $this->parseMonth($this->month),
            year: $this->parseYear($this->year),
            start: $this->parseDate($this->startDate),
            end: $this->parseDate($this->endDate),
        );
    }

    /**
     * @return array{
     *     totals: array{completed: int, links_issued: int, phone_changed: int},
     *     by_supervisor: list<array{
     *         id: int, name: string, link_registered: int, link_other: int,
     *         phone_changed: int, completed: int, total: int,
     *     }>,
     * }
     */
    #[Computed]
    public function summary(): array
    {
        [$start, $end] = $this->range();

        return app(RecoveryStatsService::class)->summarize($start, $end);
    }

    private function parseDate(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, HijriDate::TIMEZONE) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function parseMonth(string $value): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m', $value, HijriDate::TIMEZONE) ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    private function parseYear(string $value): ?int
    {
        return ctype_digit($value) ? (int) $value : null;
    }
}
