<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * سياسة تنفرد بقرار قدرات معيّنة، فلا يتجاوزها منح المدير الضمني ولا منح المشرف في Gate::before.
 * الحساب المعطَّل يبقى ممنوعًا منها كغيرها.
 */
interface ReservesAbilitiesToPolicy
{
    /**
     * @return list<string>
     */
    public function abilitiesReservedToPolicy(): array;
}
