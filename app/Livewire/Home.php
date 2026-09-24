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
 * الصفحة الرئيسية / (docs/SPEC.md §1, §7, FR-31, FR-34).
 */
class Home extends Component
{
    #[Computed]
    public function availableCount(): int
    {
        return Beneficiary::available()->count();
    }

    #[Computed]
    public function closedCount(): int
    {
        return Beneficiary::closed()->count();
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
        return view('livewire.home')
            ->title(config('app.name'))
            ->layoutData(['description' => __('site.meta.description')]);
    }
}
