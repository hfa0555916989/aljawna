<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Beneficiary;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * الصفحة الرئيسية / (docs/SPEC.md §1, FR-34).
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

    public function render(): View
    {
        return view('livewire.home')
            ->title(config('app.name'))
            ->layoutData(['description' => __('site.meta.description')]);
    }
}
