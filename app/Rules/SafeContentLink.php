<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\SafeLink;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * رابط http أو https أو tel أو مسار داخلي في الموقع فقط (App\Support\SafeLink).
 * الحقل الفارغ يُترك لقاعدة required.
 */
class SafeContentLink implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_string($value) || ! SafeLink::isAllowed(trim($value))) {
            $fail('pages.validation.link')->translate();
        }
    }
}
