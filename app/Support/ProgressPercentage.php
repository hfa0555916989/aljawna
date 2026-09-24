<?php

declare(strict_types=1);

namespace App\Support;

/**
 * نسبة المجموع من المستهدف بعدد صحيح بين 0 و100 (docs/SPEC.md §7): تُقرَّب للأدنى،
 * فلا تظهر 100% قبل بلوغ المستهدف، وتبقى 100% عند تجاوزه.
 */
final class ProgressPercentage
{
    private function __construct()
    {
        //
    }

    /**
     * @param  numeric-string  $collected
     * @param  numeric-string  $target
     */
    public static function of(string $collected, string $target): int
    {
        if (bccomp($target, '0', 2) <= 0 || bccomp($collected, '0', 2) <= 0) {
            return 0;
        }

        return min(100, (int) bcdiv(bcmul($collected, '100', 2), $target, 0));
    }
}
