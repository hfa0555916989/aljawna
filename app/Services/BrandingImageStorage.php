<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\StorageOperationFailed;
use App\Support\BrandingImageType;
use GdImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * حفظ صور الهوية المرفوعة (شعار فاتح/داكن أو أيقونة) — docs/SPEC.md §12.13.
 *
 * النوع يُكتشف من المحتوى فعليًا لا من الامتداد، والصورة يُعاد ترميزها عبر GD
 * فتسقط عنها كل بيانات EXIF وأي محتوى غير البكسلات، وتُحفظ باسم عشوائي على
 * قرص عام (تُخدم من نطاق الموقع نفسه). يوازي App\Services\ReceiptStorage لكن
 * على قرص عام وبلا PDF ولا رابط موقّع، لأن الشعار عام لا خاص.
 *
 * نتيجة الكتابة والحذف تُفحص: القرص العام بـ throw: false (وكذلك تعريف Laravel Cloud)، فالفشل
 * يرمي StorageOperationFailed بدل أن يمرّ صامتًا، ويعرضه المستدعي رسالةً في اللوحة.
 */
class BrandingImageStorage
{
    private const int JPEG_QUALITY = 90;

    private const int PNG_COMPRESSION = 6;

    /**
     * @throws ValidationException
     * @throws StorageOperationFailed
     */
    public function store(UploadedFile $file): string
    {
        $original = (string) $file->get();
        $type = BrandingImageType::detect($original) ?? throw $this->invalid();

        $dimensions = $this->dimensionsOf($original);

        if ($dimensions === null) {
            throw $this->invalid();
        }

        if (! $this->withinPixelLimit($dimensions)) {
            throw $this->invalid('branding.validation.image_dimensions');
        }

        $contents = $this->reencode($original, $type);
        $path = Str::random(40).'.'.$type->extension();

        if ($this->disk()->put($path, $contents) !== true) {
            throw StorageOperationFailed::write($this->diskName());
        }

        return $path;
    }

    /**
     * @throws StorageOperationFailed
     */
    public function delete(?string $path): void
    {
        if ($path !== null && $this->disk()->delete($path) !== true) {
            throw StorageOperationFailed::delete($this->diskName());
        }
    }

    /**
     * @return array{int, int}|null
     */
    public function dimensionsOf(string $contents): ?array
    {
        $size = @getimagesizefromstring($contents);

        return $size !== false && $size[0] > 0 && $size[1] > 0 ? [$size[0], $size[1]] : null;
    }

    /**
     * @param  array{int, int}  $dimensions
     */
    public function withinPixelLimit(array $dimensions): bool
    {
        return (int) config('security.branding.max_pixels') >= $dimensions[0] * $dimensions[1];
    }

    public function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk($this->diskName());
    }

    private function diskName(): string
    {
        return (string) config('security.branding.disk');
    }

    /**
     * @throws ValidationException
     */
    private function reencode(string $original, BrandingImageType $type): string
    {
        $image = @imagecreatefromstring($original);

        if (! $image instanceof GdImage) {
            throw $this->invalid();
        }

        $stream = fopen('php://memory', 'w+b');

        if ($stream === false) {
            throw $this->invalid();
        }

        try {
            if ($type === BrandingImageType::Png) {
                imagesavealpha($image, true);
                $written = imagepng($image, $stream, self::PNG_COMPRESSION);
            } else {
                imageinterlace($image, true);
                $written = imagejpeg($image, $stream, self::JPEG_QUALITY);
            }

            rewind($stream);
            $contents = stream_get_contents($stream);
        } finally {
            fclose($stream);
        }

        if (! $written || $contents === false || $contents === '') {
            throw $this->invalid();
        }

        return $contents;
    }

    private function invalid(string $key = 'branding.validation.image_type'): ValidationException
    {
        return ValidationException::withMessages(['image' => __($key)]);
    }
}
