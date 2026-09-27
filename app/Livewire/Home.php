<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Beneficiary;
use App\Services\BaseDesign;
use App\Services\PageRenderer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * الصفحة الرئيسية / (docs/SPEC.md §1, §7, FR-31, FR-34, FR-50).
 *
 * محتواها كتل آخر نسخة منشورة للصفحة النظامية في منشئ الصفحات، وأول مرة
 * "التصميم الأساسي" (App\Services\BaseDesign). العدّادات وأحدث الحوالات وبطاقات
 * المبادرات تُحسب عند كل عرض من قاعدة البيانات لا من الكتل.
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
        $home = app(BaseDesign::class)->home();

        return view('livewire.home', [
            'blocks' => app(PageRenderer::class)->prepare($home->liveBlocks() ?? []),
            'pageTitle' => $home->title,
        ])
            ->title($home->title)
            ->layoutData(['description' => $home->seo_description ?? __('site.meta.description')]);
    }
}
