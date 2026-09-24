<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use Throwable;

/**
 * عرض التواريخ بالهجري (أم القرى) والميلادي معًا، والتحويل العكسي، والعدّ التنازلي (docs/SPEC.md §8).
 *
 * التواريخ تُخزَّن ميلادية، وتُعرض بتوقيت الرياض.
 */
final class HijriDate
{
    private const string TIMEZONE = 'Asia/Riyadh';

    /**
     * أقصى فرق بالأيام بين التقدير الحسابي وتقويم أم القرى عند التحويل العكسي.
     */
    private const int SEARCH_WINDOW_DAYS = 40;

    /**
     * ١ محرم ١ هـ بالتقويم الميلادي الممتد (١٦ يوليو ٦٢٢ يوليانيًا).
     */
    private const string HIJRI_EPOCH = '0622-07-19';

    private function __construct()
    {
        //
    }

    /**
     * التاريخ الهجري بصيغة طويلة، مثل "١١ ربيع الآخر ١٤٤٨ هـ"، أو null لقيمة فارغة أو غير صالحة.
     */
    public static function format(DateTimeInterface|string|null $date): ?string
    {
        $day = self::toDay($date);

        return $day === null ? null : self::render('ar_SA@calendar=islamic-umalqura', IntlDateFormatter::TRADITIONAL, $day);
    }

    /**
     * التاريخ الميلادي بصيغة طويلة، مثل "١٦ يونيو ٢٠٢٦م".
     */
    public static function gregorian(DateTimeInterface|string|null $date): ?string
    {
        $day = self::toDay($date);
        $formatted = $day === null ? null : self::render('ar_SA@calendar=gregorian', IntlDateFormatter::GREGORIAN, $day);

        return $formatted === null ? null : $formatted.'م';
    }

    /**
     * اسم اليوم، مثل "الثلاثاء".
     */
    public static function weekday(DateTimeInterface|string|null $date): ?string
    {
        $day = self::toDay($date);

        return $day === null ? null : self::render('ar_SA', IntlDateFormatter::GREGORIAN, $day, 'EEEE');
    }

    /**
     * التاريخ بالتقويمين مع اسم اليوم، أو null لقيمة فارغة أو غير صالحة.
     *
     * @return array{hijri: string, gregorian: string, weekday: string}|null
     */
    public static function dual(DateTimeInterface|string|null $date): ?array
    {
        $hijri = self::format($date);
        $gregorian = self::gregorian($date);
        $weekday = self::weekday($date);

        if ($hijri === null || $gregorian === null || $weekday === null) {
            return null;
        }

        return ['hijri' => $hijri, 'gregorian' => $gregorian, 'weekday' => $weekday];
    }

    /**
     * يحوّل تاريخًا هجريًا (أم القرى) إلى ميلادي، أو null إن لم يوجد هذا اليوم (مثل ٣٠ في شهر من ٢٩ يومًا).
     */
    public static function toGregorian(int $year, int $month, int $day): ?CarbonImmutable
    {
        if ($year < 1 || $month < 1 || $month > 12 || $day < 1 || $day > 30) {
            return null;
        }

        $formatter = new IntlDateFormatter(
            'en_US@calendar=islamic-umalqura;numbers=latn',
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            self::TIMEZONE,
            IntlDateFormatter::TRADITIONAL,
            'y-M-d',
        );

        $wanted = "{$year}-{$month}-{$day}";
        $daysSinceHijriEpoch = ($year - 1) * 354.36707 + ($month - 1) * 29.530589 + ($day - 1);
        $estimate = (new CarbonImmutable(self::HIJRI_EPOCH, self::TIMEZONE))
            ->addDays((int) round($daysSinceHijriEpoch));

        for ($offset = -self::SEARCH_WINDOW_DAYS; $offset <= self::SEARCH_WINDOW_DAYS; $offset++) {
            $candidate = $estimate->addDays($offset);

            if ($formatter->format($candidate) === $wanted) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * عدد الأيام من اليوم (بتوقيت الرياض) حتى التاريخ؛ سالب إن كان قد مضى.
     */
    public static function daysUntil(DateTimeInterface|string $date): ?int
    {
        $day = self::toDay($date);

        if ($day === null) {
            return null;
        }

        return (int) CarbonImmutable::now(self::TIMEZONE)->startOfDay()->diffInDays($day, false);
    }

    /**
     * العدّ التنازلي بصياغة عربية، مثل "المتبقي: يومان" أو "المتبقي: 11 يومًا" أو "انتهى الموعد".
     */
    public static function countdown(DateTimeInterface|string|null $date): ?string
    {
        $days = $date === null ? null : self::daysUntil($date);

        if ($days === null) {
            return null;
        }

        if ($days < 0) {
            return __('dates.countdown.passed');
        }

        if ($days === 0) {
            return __('dates.countdown.today');
        }

        return __('dates.countdown.remaining', ['days' => trans_choice('dates.days', $days)]);
    }

    private static function toDay(DateTimeInterface|string|null $date): ?CarbonImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            $day = $date instanceof DateTimeInterface
                ? CarbonImmutable::instance($date)
                : CarbonImmutable::parse($date, self::TIMEZONE);
        } catch (Throwable) {
            return null;
        }

        return $day->setTimezone(self::TIMEZONE)->startOfDay();
    }

    private static function render(string $locale, int $calendar, CarbonImmutable $day, ?string $pattern = null): ?string
    {
        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE,
            self::TIMEZONE,
            $calendar,
            $pattern,
        );

        $formatted = $formatter->format($day);

        return $formatted === false ? null : $formatted;
    }
}
