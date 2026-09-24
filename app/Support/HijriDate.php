<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;
use Illuminate\Support\Carbon;
use IntlDateFormatter;
use Throwable;

/**
 * عرض التاريخ بالهجري وفق تقويم أم القرى (docs/SPEC.md §8).
 *
 * نسخة أولية لمعاينة التواريخ في نموذج المستفيد (T05)، وتُستكمل في T06
 * بالعرض المزدوج واسم اليوم والتحويل العكسي والعدّ التنازلي.
 */
final class HijriDate
{
    private function __construct()
    {
        //
    }

    /**
     * التاريخ الهجري بصيغة طويلة، مثل "١١ ربيع الآخر ١٤٤٨ هـ"، أو null لقيمة فارغة أو غير صالحة.
     */
    public static function format(DateTimeInterface|string|null $date): ?string
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $gregorian = $date instanceof DateTimeInterface
                ? Carbon::instance($date)
                : Carbon::parse($date, 'Asia/Riyadh');
        } catch (Throwable) {
            return null;
        }

        $formatter = new IntlDateFormatter(
            'ar_SA@calendar=islamic-umalqura',
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            'Asia/Riyadh',
            IntlDateFormatter::TRADITIONAL,
        );

        $formatted = $formatter->format($gregorian->setTimezone('Asia/Riyadh')->startOfDay());

        return $formatted === false ? null : $formatted;
    }
}
