<?php

declare(strict_types=1);

namespace App\Support;

use finfo;

/**
 * أنواع صورة الهوية المسموحة عند رفع شعار أو أيقونة جديدة (docs/SPEC.md §12.13): JPG وPNG.
 *
 * لا SVG هنا: ملفات الشعار المعتمدة (resources/images/brand) صور ثابتة معتمدة
 * تُقدَّم كما هي، أما رفع مستقبلي فيُقبل صورًا نقطية فقط لأن SVG نص XML قابل
 * لتضمين سكربتات، والنوع يُكتشف من محتوى الملف فعليًا لا من الامتداد.
 */
enum BrandingImageType: string
{
    case Jpeg = 'image/jpeg';
    case Png = 'image/png';

    public const EXTENSIONS = ['jpg' => self::Jpeg, 'jpeg' => self::Jpeg, 'png' => self::Png];

    public static function detect(string $contents): ?self
    {
        $type = self::tryFrom((string) (new finfo(FILEINFO_MIME_TYPE))->buffer($contents));

        return match ($type) {
            self::Jpeg => str_starts_with($contents, "\xFF\xD8\xFF") ? $type : null,
            self::Png => str_starts_with($contents, "\x89PNG\r\n\x1A\n") ? $type : null,
            null => null,
        };
    }

    public static function fromExtension(string $extension): ?self
    {
        return self::EXTENSIONS[strtolower($extension)] ?? null;
    }

    public function extension(): string
    {
        return match ($this) {
            self::Jpeg => 'jpg',
            self::Png => 'png',
        };
    }
}
