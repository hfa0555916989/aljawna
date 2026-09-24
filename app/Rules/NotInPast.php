<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;
use Throwable;

/**
 * التاريخ اليوم أو بعده بتوقيت التطبيق (Asia/Riyadh).
 */
class NotInPast implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        try {
            $date = Carbon::parse($value)->startOfDay();
        } catch (Throwable) {
            return;
        }

        if ($date->lt(today())) {
            $fail('beneficiaries.validation.date_in_past')->translate();
        }
    }
}
