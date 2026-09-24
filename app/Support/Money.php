<?php

declare(strict_types=1);

namespace App\Support;

/**
 * عرض المبالغ المخزّنة decimal(12,2) دون المرور بـ float، مثل "50,000 ر.س" أو "1,250.50 ر.س".
 */
final class Money
{
    private function __construct()
    {
        //
    }

    /**
     * @param  numeric-string  $amount
     */
    public static function format(string $amount): string
    {
        $normalized = bcadd($amount, '0', 2);
        $negative = str_starts_with($normalized, '-');
        [$whole, $fraction] = explode('.', ltrim($normalized, '-'));

        $grouped = strrev(implode(',', str_split(strrev($whole), 3)));
        $number = ($negative ? '-' : '').$grouped.($fraction === '00' ? '' : '.'.$fraction);

        return $number.' '.__('beneficiaries.currency');
    }
}
