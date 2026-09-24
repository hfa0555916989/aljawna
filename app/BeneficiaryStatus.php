<?php

declare(strict_types=1);

namespace App;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة مبادرة المستفيد (docs/SPEC.md §9 beneficiaries, FR-32).
 * المغلقة لا تستقبل حوالات جديدة ولا تعرض حسابها.
 */
enum BeneficiaryStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __('beneficiaries.status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Closed => 'gray',
        };
    }
}
