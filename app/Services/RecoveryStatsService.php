<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\RecoveryLogAction;
use App\Support\HijriDate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * إحصائيات الاستعادة بالفترات، وحدودها الزمنية بتوقيت الرياض (docs/SPEC.md §4.4, FR-42).
 *
 * الأرقام مصدرها: `recovery_logs` لعدد الروابط الصادرة وتعديلات أرقام الدخول (حسب `action`
 * و`performed_by`)، و`password_reset_tokens.used_at` لعدد الاستعادات المنجزة فعليًا (تغيير
 * كلمة المرور)، منسوبة إلى المشرف الذي أصدر الرابط المُستخدَم (`issued_by`)؛ فلا يوجد إجراء
 * "اكتمل" في `recovery_logs` نفسه (الإكمال يقوم به صاحب الطلب لا مشرف).
 */
final class RecoveryStatsService
{
    public const string PERIOD_DAY = 'day';

    public const string PERIOD_MONTH = 'month';

    public const string PERIOD_YEAR = 'year';

    public const string PERIOD_CUSTOM = 'custom';

    /**
     * حدود الفترة [البداية، النهاية) بتوقيت الرياض؛ النهاية غير شاملة (بداية اليوم التالي
     * لآخر يوم في الفترة)، لضمان دقة حدود اليوم والشهر والسنة عند منتصف الليل.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function boundsFor(
        string $period,
        ?CarbonImmutable $day = null,
        ?CarbonImmutable $month = null,
        ?int $year = null,
        ?CarbonImmutable $start = null,
        ?CarbonImmutable $end = null,
    ): array {
        $now = CarbonImmutable::now(HijriDate::TIMEZONE);

        return match ($period) {
            self::PERIOD_MONTH => $this->monthBounds($month ?? $now),
            self::PERIOD_YEAR => $this->yearBounds($year ?? $now->year),
            self::PERIOD_CUSTOM => $this->customBounds($start ?? $now, $end ?? $start ?? $now),
            default => $this->dayBounds($day ?? $now),
        };
    }

    /**
     * مؤشرات الاستعادة خلال الفترة: الإجمالي، وتفصيل حسب المشرف ونوع الإجراء (docs/SPEC.md §4.4).
     *
     * @return array{
     *     totals: array{completed: int, links_issued: int, phone_changed: int},
     *     by_supervisor: list<array{
     *         id: int, name: string, link_registered: int, link_other: int,
     *         phone_changed: int, completed: int, total: int,
     *     }>,
     * }
     */
    public function summarize(CarbonImmutable $start, CarbonImmutable $end): array
    {
        /** @var iterable<int, object{performed_by: int, action: string, total: int|string}> $logRows */
        $logRows = DB::table('recovery_logs')
            ->selectRaw('performed_by, action, count(*) as total')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->groupBy('performed_by', 'action')
            ->get();

        /** @var iterable<int, object{issued_by: int, total: int|string}> $completedRows */
        $completedRows = DB::table('password_reset_tokens')
            ->selectRaw('issued_by, count(*) as total')
            ->whereNotNull('used_at')
            ->where('used_at', '>=', $start)
            ->where('used_at', '<', $end)
            ->groupBy('issued_by')
            ->get();

        $totals = ['completed' => 0, 'links_issued' => 0, 'phone_changed' => 0];

        /** @var array<int, array{id: int, link_registered: int, link_other: int, phone_changed: int, completed: int, total: int}> $bySupervisor */
        $bySupervisor = [];

        foreach ($logRows as $row) {
            $id = (int) $row->performed_by;
            $count = (int) $row->total;
            $action = RecoveryLogAction::from((string) $row->action);

            $bySupervisor[$id] ??= $this->emptyRow($id);
            $bySupervisor[$id][$action->value] += $count;
            $bySupervisor[$id]['total'] += $count;

            if ($action === RecoveryLogAction::PhoneChanged) {
                $totals['phone_changed'] += $count;
            } else {
                $totals['links_issued'] += $count;
            }
        }

        foreach ($completedRows as $row) {
            $id = (int) $row->issued_by;
            $count = (int) $row->total;

            $bySupervisor[$id] ??= $this->emptyRow($id);
            $bySupervisor[$id]['completed'] += $count;
            $bySupervisor[$id]['total'] += $count;
            $totals['completed'] += $count;
        }

        $names = User::query()->whereIn('id', array_keys($bySupervisor))->pluck('full_name', 'id');

        $breakdown = array_values(array_map(
            fn (array $row): array => [...$row, 'name' => (string) ($names[$row['id']] ?? '')],
            $bySupervisor,
        ));

        usort($breakdown, fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return [
            'totals' => $totals,
            'by_supervisor' => $breakdown,
        ];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function dayBounds(CarbonImmutable $day): array
    {
        $start = $day->timezone(HijriDate::TIMEZONE)->startOfDay();

        return [$start, $start->addDay()];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function monthBounds(CarbonImmutable $month): array
    {
        $start = $month->timezone(HijriDate::TIMEZONE)->startOfMonth()->startOfDay();

        return [$start, $start->addMonthNoOverflow()];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function yearBounds(int $year): array
    {
        $start = CarbonImmutable::createFromDate($year, 1, 1, HijriDate::TIMEZONE)->startOfDay();

        return [$start, $start->addYear()];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function customBounds(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $startDay = $start->timezone(HijriDate::TIMEZONE)->startOfDay();
        $endDay = $end->timezone(HijriDate::TIMEZONE)->startOfDay();

        if ($endDay->lessThan($startDay)) {
            [$startDay, $endDay] = [$endDay, $startDay];
        }

        return [$startDay, $endDay->addDay()];
    }

    /**
     * @return array{id: int, link_registered: int, link_other: int, phone_changed: int, completed: int, total: int}
     */
    private function emptyRow(int $id): array
    {
        return [
            'id' => $id,
            'link_registered' => 0,
            'link_other' => 0,
            'phone_changed' => 0,
            'completed' => 0,
            'total' => 0,
        ];
    }
}
