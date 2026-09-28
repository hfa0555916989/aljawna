<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * فشل في النسخ الاحتياطي أو استرجاعه (T21، docs/RUNBOOK.md). الرسائل لمن يشغّل الأوامر
 * من سطر الأوامر، ولا تحتوي مفاتيح ولا بيانات اتصال ولا محتوى النسخ.
 */
class BackupException extends RuntimeException
{
    public static function missingKey(): self
    {
        return new self('مفتاح تشفير النسخ الاحتياطي BACKUP_ENCRYPTION_KEY غير مضبوط.');
    }

    public static function invalidKey(): self
    {
        return new self('مفتاح تشفير النسخ الاحتياطي يجب أن يكون base64: متبوعًا بـ 32 بايت بترميز base64.');
    }

    public static function notABackup(): self
    {
        return new self('الملف ليس نسخة احتياطية مشفّرة من هذا النظام.');
    }

    public static function wrongKey(): self
    {
        return new self('النسخة مشفّرة بمفتاح غير المفتاح المضبوط في BACKUP_ENCRYPTION_KEY.');
    }

    public static function corrupted(): self
    {
        return new self('النسخة تالفة أو عُدِّل محتواها: فشل التحقق من سلامة التشفير.');
    }

    public static function truncated(): self
    {
        return new self('النسخة ناقصة: انتهى الملف قبل علامة النهاية.');
    }

    public static function invalidContent(string $reason): self
    {
        return new self('محتوى النسخة غير صالح: '.$reason);
    }

    public static function notFound(string $path): self
    {
        return new self('النسخة غير موجودة في وجهة النسخ الاحتياطي: '.$path);
    }

    /**
     * @param  list<string>  $missing
     */
    public static function migrationsMissing(array $missing): self
    {
        return new self('النسخة أحدث من الشيفرة الحالية، فهذه الترحيلات غير موجودة: '.implode('، ', $missing).'. انشر الإصدار المطابق أولًا.');
    }

    public static function targetNotEmpty(): self
    {
        return new self('قاعدة البيانات الهدف ليست فارغة. استرجع إلى قاعدة فارغة، أو مرّر --wipe لحذف كل جداولها أولًا.');
    }

    public static function foreignKeyCycle(): self
    {
        return new self('تعذّر ترتيب الجداول: علاقات مفاتيح أجنبية دائرية.');
    }

    public static function io(string $operation): self
    {
        return new self('تعذّر '.$operation.'.');
    }
}
