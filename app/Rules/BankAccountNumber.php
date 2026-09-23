<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\BankAccountNumber as BankAccountNumberValue;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * رقم حساب بنكي من أرقام فقط (docs/SPEC.md §12.10).
 */
class BankAccountNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BankAccountNumberValue::isValid($value)) {
            $fail('beneficiaries.validation.account_number')->translate();
        }
    }
}
