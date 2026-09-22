<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * قاعدة الاسم الكامل (docs/SPEC.md §3): أربع كلمات فأكثر، أحرف عربية أو
 * لاتينية ومسافات فقط، وكل كلمة حرفان على الأقل.
 */
class FullName implements ValidationRule
{
    private const MIN_WORDS = 4;

    private const MIN_WORD_LENGTH = 2;

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('يجب أن يكون :attribute نصًا.');

            return;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || preg_match('/^[\x{0600}-\x{06FF}a-zA-Z\s]+$/u', $trimmed) !== 1) {
            $fail('يُسمح بالأحرف العربية أو اللاتينية والمسافات فقط في :attribute.');

            return;
        }

        $words = preg_split('/\s+/u', $trimmed, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if (count($words) < self::MIN_WORDS) {
            $fail('اكتب اسمك كاملًا (أربع كلمات على الأقل).');

            return;
        }

        foreach ($words as $word) {
            if (mb_strlen($word) < self::MIN_WORD_LENGTH) {
                $fail('كل كلمة في :attribute يجب أن تكون حرفين على الأقل.');

                return;
            }
        }
    }
}
