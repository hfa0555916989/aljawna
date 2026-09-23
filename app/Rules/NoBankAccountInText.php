<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SaudiIban;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * يمنع نمط آيبان سعودي (SA يليه 22 رقمًا) داخل نص حر، حتى لا يُعرض حساب
 * خارج الحقول البنكية النظامية (.cursor/rules/30-security-privacy, §12.13).
 */
class NoBankAccountInText implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && preg_match('/SA\d{22}/', SaudiIban::normalize($value)) === 1) {
            $fail('beneficiaries.validation.no_iban_in_text')->translate();
        }
    }
}
