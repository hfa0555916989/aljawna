<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Support\ProgressPercentage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * مؤشرات الحوالات لمستفيد واحد أو لمجموع المبادرة كاملة (docs/SPEC.md §7, FR-31).
 *
 * كل حوالة تدخل الحساب فور رفع إيصالها (FR-14)، فلا تصفية بحالة مراجعة. النتائج
 * تُخزَّن مؤقتًا مدة قصيرة (security.stats.cache_seconds)، وتُبطَل فورًا عند إضافة
 * حوالة أو تعديلها (انظر App\Models\Transfer::booted()).
 */
final class StatsService
{
    public const int RECENT_LIMIT = 6;

    private const string OVERALL_KEY = 'stats:overall';

    private const string RECENT_KEY = 'stats:recent';

    private const string BENEFICIARY_KEY_PREFIX = 'stats:beneficiary:';

    /**
     * أعداد المبادرات المتاحة والمغلقة (docs/SPEC.md §7). لا علاقة لها بالحوالات
     * فلا تُخزَّن هنا مؤقتًا؛ تتغيّر باعتماد أو إغلاق مبادرة لا بإضافة حوالة.
     *
     * @return array{available: int, closed: int}
     */
    public function beneficiaryCounts(): array
    {
        return [
            'available' => Beneficiary::available()->count(),
            'closed' => Beneficiary::closed()->count(),
        ];
    }

    /**
     * مؤشرات الحوالات: لمستفيد واحد إن مُرِّر، أو لمجموع كل المستفيدين إن مرّ null.
     *
     * @return array{
     *     initiators_count: int,
     *     receipts_count: int,
     *     total: numeric-string,
     *     average: numeric-string,
     *     target: numeric-string,
     *     progress_percentage: int,
     *     remaining_percentage: int,
     * }
     */
    public function forBeneficiary(?Beneficiary $beneficiary = null): array
    {
        $key = $beneficiary === null ? self::OVERALL_KEY : self::BENEFICIARY_KEY_PREFIX.$beneficiary->id;

        /** @var array{initiators_count: int, receipts_count: int, total: numeric-string, average: numeric-string, target: numeric-string, progress_percentage: int, remaining_percentage: int} */
        return Cache::remember($key, $this->ttl(), fn (): array => $this->compute($beneficiary));
    }

    /**
     * أحدث الحوالات (docs/SPEC.md §7): الاسم الأول للمبادر فقط، واسم المستفيد كاملًا.
     *
     * @return Collection<int, Transfer>
     */
    public function recentTransfers(): Collection
    {
        // Redis هنا لا يسمح بتخزين الكائنات (config cache.serializable_classes)،
        // لذلك نخبّئ المعرّفات فقط ثم نعيد تحميل الصفوف بالترتيب نفسه.
        /** @var list<int> $ids */
        $ids = Cache::remember(self::RECENT_KEY, $this->ttl(), fn (): array => Transfer::query()
            ->latest('created_at')
            ->latest('id')
            ->limit(self::RECENT_LIMIT)
            ->pluck('id')
            ->all());

        if ($ids === []) {
            return new Collection;
        }

        $transfers = Transfer::query()
            ->select(['id', 'user_id', 'beneficiary_id', 'amount', 'created_at'])
            ->with(['user:id,full_name', 'beneficiary:id,display_name'])
            ->whereKey($ids)
            ->get()
            ->keyBy('id');

        return new Collection(array_values(array_filter(
            array_map(fn (int $id): ?Transfer => $transfers->get($id), $ids),
        )));
    }

    /**
     * إبطال كاش مستفيد واحد ومجموع الكل وأحدث الحوالات معًا (تُستدعى من Transfer::booted()).
     */
    public static function forget(int $beneficiaryId): void
    {
        Cache::forget(self::BENEFICIARY_KEY_PREFIX.$beneficiaryId);
        Cache::forget(self::OVERALL_KEY);
        Cache::forget(self::RECENT_KEY);
    }

    /**
     * @return array{
     *     initiators_count: int,
     *     receipts_count: int,
     *     total: numeric-string,
     *     average: numeric-string,
     *     target: numeric-string,
     *     progress_percentage: int,
     *     remaining_percentage: int,
     * }
     */
    private function compute(?Beneficiary $beneficiary): array
    {
        $query = DB::table('transfers');

        if ($beneficiary !== null) {
            $query->where('beneficiary_id', $beneficiary->id);
        }

        $row = $query
            ->selectRaw('count(distinct user_id) as initiators_count, count(*) as receipts_count, coalesce(sum(amount), 0) as total')
            ->first();

        $initiatorsCount = $row === null ? 0 : (int) $row->initiators_count;
        $receiptsCount = $row === null ? 0 : (int) $row->receipts_count;
        $rawTotal = $row === null ? '0' : $row->total;
        $total = bcadd(is_numeric($rawTotal) ? (string) $rawTotal : '0', '0', 2);
        $average = $receiptsCount > 0 ? bcdiv($total, (string) $receiptsCount, 2) : '0.00';

        $target = $beneficiary !== null
            ? bcadd($beneficiary->target_amount, '0', 2)
            : $this->totalTarget();

        $progress = ProgressPercentage::of($total, $target);

        return [
            'initiators_count' => $initiatorsCount,
            'receipts_count' => $receiptsCount,
            'total' => $total,
            'average' => $average,
            'target' => $target,
            'progress_percentage' => $progress,
            'remaining_percentage' => 100 - $progress,
        ];
    }

    /**
     * @return numeric-string
     */
    private function totalTarget(): string
    {
        return bcadd((string) Beneficiary::public()->sum('target_amount'), '0', 2);
    }

    private function ttl(): int
    {
        return (int) config('security.stats.cache_seconds');
    }
}
