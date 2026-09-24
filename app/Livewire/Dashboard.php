<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Services\StatsService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * لوحة المبادر /dashboard (docs/SPEC.md §7, FR-31): بطاقات المؤشرات العامة لمجموع
 * المبادرة، والمواعيد بالتقويمين للمستفيد المختار، وأحدث المبادرات، ورابط رفع الحوالة.
 */
class Dashboard extends Component
{
    public ?int $selectedBeneficiaryId = null;

    public function mount(): void
    {
        $this->selectedBeneficiaryId = $this->availableBeneficiaries()->first()?->id;
    }

    /**
     * المستفيدون المتاحون، لاختيار من يُعرَض موعده في هذه اللوحة.
     *
     * @return Collection<int, Beneficiary>
     */
    #[Computed]
    public function availableBeneficiaries(): Collection
    {
        return Beneficiary::available()->orderBy('target_deadline')->orderBy('id')->get(['id', 'display_name', 'target_deadline', 'recommended_deadline', 'wedding_date']);
    }

    #[Computed]
    public function selectedBeneficiary(): ?Beneficiary
    {
        return $this->availableBeneficiaries()->firstWhere('id', $this->selectedBeneficiaryId);
    }

    /**
     * @return array{available: int, closed: int}
     */
    #[Computed]
    public function beneficiaryCounts(): array
    {
        return app(StatsService::class)->beneficiaryCounts();
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
    #[Computed]
    public function stats(): array
    {
        return app(StatsService::class)->forBeneficiary();
    }

    /**
     * @return Collection<int, Transfer>
     */
    #[Computed]
    public function recentTransfers(): Collection
    {
        return app(StatsService::class)->recentTransfers();
    }

    public function render(): View
    {
        return view('livewire.dashboard')->title(__('dashboard.title').' — '.config('app.name'));
    }
}
