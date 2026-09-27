<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\BrandingImageStorage;
use App\Support\BrandingImageType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * صورة هوية JPG أو PNG بنوعها الحقيقي (docs/SPEC.md §12.13).
 *
 * يُرفض الملف إن لم يكن محتواه أحد الأنواع المسموحة، أو إن خالف امتداده نوعه
 * الحقيقي (امتداد مزيَّف)، أو إن تجاوزت أبعاده الحد الأقصى قبل فك ترميزه.
 */
class BrandingImageFile implements ValidationRule
{
    /**
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('branding.validation.image_type')->translate();

            return;
        }

        $declared = BrandingImageType::fromExtension($value->getClientOriginalExtension());
        $contents = (string) $value->get();
        $actual = BrandingImageType::detect($contents);

        if ($declared === null || $actual === null || $declared !== $actual) {
            $fail('branding.validation.image_type')->translate();

            return;
        }

        $storage = app(BrandingImageStorage::class);
        $dimensions = $storage->dimensionsOf($contents);

        if ($dimensions === null) {
            $fail('branding.validation.image_type')->translate();
        } elseif (! $storage->withinPixelLimit($dimensions)) {
            $fail('branding.validation.image_dimensions')->translate();
        }
    }
}
