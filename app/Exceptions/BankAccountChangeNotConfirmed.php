<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * محاولة تغيير حقل بنكي لمستفيد دون تأكيد صريح (docs/SPEC.md FR-33, §12.10).
 */
class BankAccountChangeNotConfirmed extends RuntimeException
{
    /**
     * @param  list<string>  $fields
     */
    public function __construct(public readonly array $fields)
    {
        parent::__construct(__('beneficiaries.bank_change.not_confirmed'));
    }
}
