<?php

declare(strict_types=1);

namespace App\Support;

use finfo;

/**
 * أنواع الإيصال المسموحة (docs/SPEC.md FR-13): JPG وPNG وPDF.
 *
 * النوع يُكتشف من محتوى الملف فعليًا (finfo مع التوقيع السحري للصيغة)، لا من الامتداد ولا مما يرسله المتصفح (§12.6).
 */
enum ReceiptType: string
{
    case Jpeg = 'image/jpeg';
    case Png = 'image/png';
    case Pdf = 'application/pdf';

    /**
     * الامتدادات المقبولة في اسم الملف، ويجب أن تطابق النوع الفعلي.
     */
    public const EXTENSIONS = ['jpg' => self::Jpeg, 'jpeg' => self::Jpeg, 'png' => self::Png, 'pdf' => self::Pdf];

    public static function detect(string $contents): ?self
    {
        $type = self::tryFrom((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($contents));

        return match ($type) {
            self::Jpeg => str_starts_with($contents, "\xFF\xD8\xFF") ? $type : null,
            self::Png => str_starts_with($contents, "\x89PNG\r\n\x1A\n") ? $type : null,
            self::Pdf => str_starts_with($contents, '%PDF-') ? $type : null,
            null => null,
        };
    }

    public static function fromExtension(string $extension): ?self
    {
        return self::EXTENSIONS[strtolower($extension)] ?? null;
    }

    public static function fromPath(string $path): ?self
    {
        return self::fromExtension(pathinfo($path, PATHINFO_EXTENSION));
    }

    public function extension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Png => 'png',
            self::Pdf => 'pdf',
        };
    }

    public function isImage(): bool
    {
        return $this !== self::Pdf;
    }
}
