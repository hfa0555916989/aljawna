<?php

declare(strict_types=1);

namespace App\Livewire\Beneficiaries;

use App\Models\Beneficiary;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * قائمة المبادرات /beneficiaries بتبويبين: المتاحة والمغلقة (docs/SPEC.md FR-28, FR-35).
 *
 * لا تُحمَّل الأعمدة البنكية هنا إطلاقًا؛ الحساب يُعرض في صفحة المستفيد المتاح فقط.
 */
class Index extends Component
{
    public const TAB_AVAILABLE = 'available';

    public const TAB_CLOSED = 'closed';

    /**
     * الأعمدة الآمنة للعرض العام.
     */
    private const PUBLIC_COLUMNS = [
        'id', 'display_name', 'target_amount', 'target_deadline', 'recommended_deadline', 'status', 'approved_at',
    ];

    #[Url(as: 'tab', except: self::TAB_AVAILABLE)]
    public string $tab = self::TAB_AVAILABLE;

    #[Computed]
    public function activeTab(): string
    {
        return $this->tab === self::TAB_CLOSED ? self::TAB_CLOSED : self::TAB_AVAILABLE;
    }

    /**
     * @return Collection<int, Beneficiary>
     */
    #[Computed]
    public function beneficiaries(): Collection
    {
        $query = $this->activeTab() === self::TAB_CLOSED
            ? Beneficiary::closed()->orderByDesc('target_deadline')
            : Beneficiary::available()->orderBy('target_deadline');

        return $query->orderBy('id')->get(self::PUBLIC_COLUMNS);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        return [
            self::TAB_AVAILABLE => Beneficiary::available()->count(),
            self::TAB_CLOSED => Beneficiary::closed()->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.beneficiaries.index')
            ->title(__('site.beneficiaries.title').' — '.config('app.name'))
            ->layoutData(['description' => __('site.beneficiaries.description')]);
    }
}
