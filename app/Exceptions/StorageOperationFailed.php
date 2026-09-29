<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * فشلت عملية تخزين (كتابة أو حذف) على قرص الإيصالات أو الصور العامة.
 *
 * على Laravel Cloud يأتي تعريف القرصين من LARAVEL_CLOUD_DISK_CONFIG بـ throw: false، فتعيد
 * put() وdelete() القيمة false بدل رمي استثناء. هذا الاستثناء يجعل الفشل صريحًا فلا يمرّ
 * صامتًا (docs/RUNBOOK.md القسم 6). الرسالة للسجل وسطر الأوامر، بلا مسارات ولا محتوى.
 */
class StorageOperationFailed extends RuntimeException
{
    public static function write(string $disk): self
    {
        return new self("تعذّرت الكتابة على القرص {$disk}.");
    }

    public static function delete(string $disk): self
    {
        return new self("تعذّر الحذف من القرص {$disk}.");
    }
}
