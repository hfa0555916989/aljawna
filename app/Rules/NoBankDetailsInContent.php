<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\BankDetailsInText;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * يمنع آيبانًا سعوديًا أو رقم حساب في محتوى الصفحات والقوائم (docs/SPEC.md FR-53).
 * يفحص النص الظاهر، فيعمل على النص العادي والنص المنسّق (HTML) معًا.
 */
class NoBankDetailsInContent implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if (BankDetailsInText::containsIban($value)) {
            $fail('pages.validation.iban_in_text')->translate();
        } elseif (BankDetailsInText::containsAccountNumber($value)) {
            $fail('pages.validation.account_in_text')->translate();
        }
    }
}
