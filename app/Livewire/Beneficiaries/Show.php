<?php

declare(strict_types=1);

namespace App\Livewire\Beneficiaries;

use App\Models\Beneficiary;
use App\Support\HijriDate;
use App\Support\Money;
use App\Support\SaudiIban;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * صفحة المستفيد /beneficiaries/{id} (docs/SPEC.md FR-25, FR-26, FR-28, FR-37).
 *
 * تُفتح الصفحة لكل مستفيد موجود بمعلوماته العامة (قرار T06)، أما بيانات الحساب فللمعتمد المتاح فقط.
 * بيانات الحساب لا تُحفظ في حالة المكوّن (فلا تصل لقطة Livewire)، وتُمرَّر للعرض فقط عند acceptsTransfers().
 */
class Show extends Component
{
    #[Locked]
    public int $beneficiaryId;

    public function mount(Beneficiary $beneficiary): void
    {
        $this->beneficiaryId = $beneficiary->id;
    }

    #[Computed]
    public function beneficiary(): Beneficiary
    {
        return Beneficiary::query()->findOrFail($this->beneficiaryId);
    }

    public function render(): View
    {
        $beneficiary = $this->beneficiary();

        return view('livewire.beneficiaries.show', [
            'beneficiary' => $beneficiary,
            'account' => $beneficiary->acceptsTransfers() ? [
                'bank_name' => $beneficiary->bank_name,
                'account_holder' => $beneficiary->account_holder,
                'account_number' => $beneficiary->account_number,
                'iban' => $beneficiary->iban,
                'iban_grouped' => SaudiIban::grouped($beneficiary->iban),
            ] : null,
        ])
            ->title($beneficiary->display_name.' — '.config('app.name'))
            ->layoutData(['description' => __('site.show.description', [
                'name' => $beneficiary->display_name,
                'amount' => Money::format($beneficiary->target_amount),
                'deadline' => HijriDate::format($beneficiary->target_deadline),
            ])]);
    }
}
