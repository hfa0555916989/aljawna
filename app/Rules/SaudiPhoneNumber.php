<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SaudiPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * رقم جوال سعودي صالح وفق App\Support\SaudiPhone.
 */
class SaudiPhoneNumber implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! SaudiPhone::isValid($value)) {
            $fail('auth.phone_invalid')->translate();
        }
    }
}
