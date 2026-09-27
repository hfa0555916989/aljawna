<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * عدّاد الأخطاء المسجَّلة (كل استثناء يُبلَّغ عنه) في دلاء زمنية بالتخزين المؤقت،
 * لصفحة "صحة النظام" وتنبيه الارتفاع المفاجئ. لا يقرأ ملفات السجل من القرص،
 * ولا يحفظ نص الخطأ (قد يحوي بيانات شخصية): عدد فقط (docs/DECISIONS.md).
 */
class ErrorCounter
{
    public const int BUCKET_MINUTES = 5;

    private const string PREFIX = 'monitoring:errors:';

    public function record(): void
    {
        try {
            $key = $this->bucketKey(now()->getTimestamp());

            Cache::add($key, 0, now()->addHours(25));
            Cache::increment($key);
        } catch (Throwable) {
            // تعطّل التخزين المؤقت لا يجوز أن يولّد خطأً أثناء الإبلاغ عن خطأ.
        }
    }

    /**
     * عدد الأخطاء خلال آخر $minutes دقيقة تقريبًا (بدقة الدلو)، أو null إن تعذّرت القراءة.
     */
    public function countSince(int $minutes): ?int
    {
        $buckets = max(1, (int) ceil($minutes / self::BUCKET_MINUTES));
        $now = now()->getTimestamp();
        $keys = [];

        for ($index = 0; $index < $buckets; $index++) {
            $keys[] = $this->bucketKey($now - $index * self::BUCKET_MINUTES * 60);
        }

        try {
            return (int) array_sum(array_map(intval(...), Cache::many($keys)));
        } catch (Throwable) {
            return null;
        }
    }

    public function lastDay(): ?int
    {
        return $this->countSince(24 * 60);
    }

    private function bucketKey(int $timestamp): string
    {
        return self::PREFIX.intdiv($timestamp, self::BUCKET_MINUTES * 60);
    }
}
