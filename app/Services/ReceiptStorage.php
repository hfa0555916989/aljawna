<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Transfer;
use App\Support\ReceiptType;
use App\Support\StoredReceipt;
use GdImage;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * حفظ الإيصالات وتقديمها (docs/SPEC.md §12.6).
 *
 * - النوع يُكتشف من المحتوى فعليًا، ويُحفظ الملف بامتداد نوعه الحقيقي.
 * - الصور يُعاد ترميزها عبر GD، فتسقط بيانات EXIF وكل ما سوى البكسلات، بعد تطبيق اتجاه الصورة.
 * - الحفظ على قرص خاص باسم عشوائي، والعرض عبر رابط موقّع مؤقت بعد فحص Policy.
 * - لا يُسجَّل شيء من محتوى الإيصال في السجلات.
 */
class ReceiptStorage
{
    public const MAX_KILOBYTES = 5120;

    private const JPEG_QUALITY = 90;

    private const PNG_COMPRESSION = 6;

    /**
     * @throws ValidationException
     */
    public function store(UploadedFile $file): StoredReceipt
    {
        $original = (string) $file->get();
        $type = ReceiptType::detect($original) ?? throw $this->invalid('receipt_type');

        $contents = $type->isImage() ? $this->reencode($original, $type) : $original;
        $path = Str::random(40).'.'.$type->extension();

        $this->disk()->put($path, $contents);

        return new StoredReceipt($path, hash('sha256', $original));
    }

    public function delete(string $path): void
    {
        $this->disk()->delete($path);
    }

    public function exists(string $path): bool
    {
        return $this->disk()->exists($path);
    }

    /**
     * أبعاد الصورة من ترويستها دون فك ترميزها، أو null إن لم تكن صورة سليمة الترويسة.
     *
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
        return (int) config('security.receipts.max_pixels') >= $dimensions[0] * $dimensions[1];
    }

    /**
     * رابط عرض موقّع ينتهي بعد مدة قصيرة. المسار نفسه يفحص Policy عند كل طلب.
     */
    public function temporaryUrl(Transfer $transfer): string
    {
        return URL::temporarySignedRoute(
            'transfers.receipt',
            now()->addMinutes((int) config('security.receipts.link_minutes')),
            ['transfer' => $transfer->id],
        );
    }

    /**
     * يقدّم الإيصال: الصور داخل الصفحة وPDF تنزيلًا، مع منع التخمين والتخزين المؤقت وتنفيذ أي محتوى نشط.
     */
    public function response(Transfer $transfer): StreamedResponse
    {
        $type = ReceiptType::fromPath($transfer->receipt_path);

        abort_if($type === null || ! $this->exists($transfer->receipt_path), 404);

        return $this->disk()->response(
            $transfer->receipt_path,
            'receipt-'.$transfer->id.'.'.$type->extension(),
            [
                'Content-Type' => $type->value,
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, no-store, max-age=0',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; sandbox",
                'Referrer-Policy' => 'no-referrer',
            ],
            $type->isImage() ? 'inline' : 'attachment',
        );
    }

    public function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter */
        return Storage::disk((string) config('security.receipts.disk'));
    }

    /**
     * @throws ValidationException
     */
    private function reencode(string $original, ReceiptType $type): string
    {
        $dimensions = $this->dimensionsOf($original) ?? throw $this->invalid('receipt_type');

        if (! $this->withinPixelLimit($dimensions)) {
            throw $this->invalid('receipt_dimensions');
        }

        $image = @imagecreatefromstring($original);

        if (! $image instanceof GdImage) {
            throw $this->invalid('receipt_unreadable');
        }

        if ($type === ReceiptType::Jpeg) {
            $image = $this->applyExifOrientation($image, $original);
        }

        $stream = fopen('php://memory', 'w+b');

        if ($stream === false) {
            throw $this->invalid('receipt_unreadable');
        }

        try {
            if ($type === ReceiptType::Png) {
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
            throw $this->invalid('receipt_unreadable');
        }

        return $contents;
    }

    /**
     * يطبّق اتجاه الصورة المسجّل في EXIF قبل إسقاطه، حتى لا يظهر الإيصال مقلوبًا أو مائلًا.
     */
    private function applyExifOrientation(GdImage $image, string $original): GdImage
    {
        $stream = fopen('php://memory', 'w+b');

        if ($stream === false) {
            return $image;
        }

        fwrite($stream, $original);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $orientation = is_array($exif) && isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;

        if (in_array($orientation, [2, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 8 => 90,
            6, 7 => -90,
            default => 0,
        };

        if ($orientation === 4) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function invalid(string $reason): ValidationException
    {
        return ValidationException::withMessages(['receipt' => __('transfers.validation.'.$reason)]);
    }
}
