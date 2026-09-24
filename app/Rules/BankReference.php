<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\BankReference as BankReferenceValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * رقم عملية مصرفية اختياري: حروف لاتينية وأرقام وشرطات.
 */
class BankReference implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BankReferenceValue::isValid($value)) {
            $fail('transfers.validation.bank_reference')->translate();
        }
    }
}
