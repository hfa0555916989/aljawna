<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\AmountInput;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * مبلغ حوالة أكبر من صفر بخانتين عشريتين على الأكثر (docs/SPEC.md §9 transfers).
 */
class TransferAmount implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || AmountInput::normalize($value) === null) {
            $fail('transfers.validation.amount')->translate();
        }
    }
}
