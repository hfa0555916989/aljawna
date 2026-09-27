<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SaudiPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * يمنع رقم جوال سعودي داخل نص حر، حتى لا تُحفظ الأرقام في السجلات
 * (.cursor/rules/30-security-privacy).
 */
class NoPhoneInText implements ValidationRule
{
    public function __construct(private readonly string $messageKey) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && SaudiPhone::appearsIn($value)) {
            $fail($this->messageKey)->translate();
        }
    }
}
