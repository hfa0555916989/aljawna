<?php

declare(strict_types=1);

namespace App\Services;

use App\Livewire\Beneficiaries\Index as BeneficiaryIndex;
use App\Models\Beneficiary;
use App\Support\Money;
use App\Support\PageBlocks;
use App\Support\SafeHtml;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * يجهّز كتل صفحة للعرض (docs/SPEC.md FR-50..56).
 *
 * البيانات النظامية تُقرأ هنا لا من الكتلة: العدّادات من StatsService، وبطاقات
 * المبادرات من الأعمدة العامة الآمنة فقط (بلا أي عمود بنكي)، فعرض المستفيدين
 * وحساباتهم قالب نظامي مقفل لا يغيّره المنشئ (قرار المالك في docs/DECISIONS.md).
 * النص المنسّق يُنظَّف مرة أخرى عند العرض احتياطًا.
 */
class PageRenderer
{
    public function __construct(private readonly StatsService $stats) {}

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{type: string, data: array<string, mixed>, system: array<string, mixed>}>
     */
    public function prepare(array $blocks): array
    {
        $prepared = [];

        foreach ($blocks as $block) {
            $type = $block['type'];

            if (! in_array($type, PageBlocks::TYPES, true)) {
                continue;
            }

            $prepared[] = ['type' => $type, 'data' => $block['data'], 'system' => $this->systemData($type, $block['data'])];
        }

        return $prepared;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function systemData(string $type, array $data): array
    {
        return match ($type) {
            PageBlocks::HERO => [
                'counts' => ($data['show_counters'] ?? false) ? $this->stats->beneficiaryCounts() : null,
                'buttons' => $this->visibleButtons($data['buttons'] ?? []),
            ],
            PageBlocks::RICH_TEXT => ['html' => SafeHtml::clean(is_string($data['body'] ?? null) ? $data['body'] : '')],
            PageBlocks::IMAGE => ['url' => is_string($data['path'] ?? null)
                ? Storage::disk((string) config('security.branding.disk'))->url($data['path'])
                : null],
            PageBlocks::COUNTERS => ['values' => $this->metricValues()],
            PageBlocks::INITIATIVES => ($data['mode'] ?? null) === PageBlocks::INITIATIVES_LATEST
                ? ['transfers' => $this->stats->recentTransfers()]
                : ['beneficiaries' => $this->availableBeneficiaries($data['limit'] ?? 3)],
            default => [],
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function visibleButtons(mixed $buttons): array
    {
        if (! is_array($buttons)) {
            return [];
        }

        return array_values(array_filter(
            $buttons,
            fn (mixed $button): bool => is_array($button) && ! (Auth::check() && ($button['guests_only'] ?? false)),
        ));
    }

    /**
     * @return array<string, string|int>
     */
    private function metricValues(): array
    {
        $counts = $this->stats->beneficiaryCounts();
        $overall = $this->stats->forBeneficiary();

        return [
            'available' => $counts['available'],
            'closed' => $counts['closed'],
            'initiators' => $overall['initiators_count'],
            'receipts' => $overall['receipts_count'],
            'total' => Money::format($overall['total']),
        ];
    }

    /**
     * @return Collection<int, Beneficiary>
     */
    private function availableBeneficiaries(mixed $limit): Collection
    {
        $limit = is_int($limit) ? max(1, min($limit, PageBlocks::MAX_INITIATIVE_CARDS)) : 3;

        return Beneficiary::available()
            ->select(BeneficiaryIndex::PUBLIC_COLUMNS)
            ->withSum('transfers', 'amount')
            ->orderBy('target_deadline')
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }
}
