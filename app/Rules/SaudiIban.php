<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SaudiIban as SaudiIbanNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * آيبان سعودي صالح: `SA` + 22 رقمًا مع خانة تحقق سليمة (docs/SPEC.md §12.10).
 */
class SaudiIban implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SaudiIbanNumber::hasValidFormat($value)) {
            $fail('beneficiaries.validation.iban_format')->translate();

            return;
        }

        if (! SaudiIbanNumber::isValid($value)) {
            $fail('beneficiaries.validation.iban_checksum')->translate();
        }
    }
}
