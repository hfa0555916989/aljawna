<?php

declare(strict_types=1);

namespace App\Rules;

use App\Services\ReceiptStorage;
use App\Support\ReceiptType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * إيصال JPG أو PNG أو PDF بنوعه الحقيقي (docs/SPEC.md FR-13, §12.6).
 *
 * يُرفض الملف إن لم يكن محتواه أحد الأنواع المسموحة، أو إن خالف امتداده نوعه الحقيقي
 * (امتداد مزيَّف)، أو إن تجاوزت أبعاد الصورة الحد قبل فك ترميزها. الحجم يُفحص بقاعدة max.
 */
class ReceiptFile implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            $fail('transfers.validation.receipt_type')->translate();

            return;
        }

        $declared = ReceiptType::fromExtension($value->getClientOriginalExtension());
        $contents = (string) $value->get();
        $actual = ReceiptType::detect($contents);

        if ($declared === null || $actual === null || $declared !== $actual) {
            $fail('transfers.validation.receipt_type')->translate();

            return;
        }

        if (! $actual->isImage()) {
            return;
        }

        $receipts = app(ReceiptStorage::class);
        $dimensions = $receipts->dimensionsOf($contents);

        if ($dimensions === null) {
            $fail('transfers.validation.receipt_type')->translate();
        } elseif (! $receipts->withinPixelLimit($dimensions)) {
            $fail('transfers.validation.receipt_dimensions')->translate();
        }
    }
}
