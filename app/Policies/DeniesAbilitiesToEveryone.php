<?php

declare(strict_types=1);

namespace App\Policies;

/**
 * سياسة تمنع قدرات معيّنة عن الجميع بلا استثناء، ولا يتجاوزها منح المدير الضمني في Gate::before.
 */
interface DeniesAbilitiesToEveryone
{
    /**
     * @return list<string>
     */
    public function abilitiesDeniedToEveryone(): array;
}
