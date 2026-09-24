<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Beneficiary;
use App\Support\SaudiIban as SaudiIbanNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * لا يتكرر الآيبان بين مستفيدين. المقارنة بعد التطبيع لأن العمود يُخزَّن مطبَّعًا،
 * والقيد الفريد في قاعدة البيانات هو الضمان الأخير.
 */
class UniqueBeneficiaryIban implements ValidationRule
{
    public function __construct(private readonly ?Beneficiary $ignore = null) {}

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

        $isTaken = Beneficiary::query()
            ->where('iban', SaudiIbanNumber::normalize($value))
            ->when($this->ignore?->exists, fn ($query) => $query->whereKeyNot($this->ignore?->getKey()))
            ->exists();

        if ($isTaken) {
            $fail('beneficiaries.validation.iban_taken')->translate();
        }
    }
}
