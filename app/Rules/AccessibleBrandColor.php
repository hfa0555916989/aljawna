<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\BrandColorPalette;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * مفتاح لون هوية من اللوحة المضبوطة (config/branding.php)، يُرفض إن كان غير
 * معرَّف أو ضعيف التباين (docs/SPEC.md FR-49): "لا يمكن حفظ ألوان ضعيفة التباين".
 */
class AccessibleBrandColor implements ValidationRule
{
    public function __construct(private readonly string $role) {}

    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! BrandColorPalette::isAccessible($this->role, $value)) {
            $fail('branding.validation.color_contrast')->translate();
        }
    }
}
