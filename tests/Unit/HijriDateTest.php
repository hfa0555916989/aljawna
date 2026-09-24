<?php

declare(strict_types=1);

use App\Support\HijriDate;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| التاريخ الهجري (أم القرى) والميلادي والعدّ التنازلي (T06 — docs/SPEC.md §8, FR-25, FR-26)
|--------------------------------------------------------------------------
*/

test('يعرض التاريخ بالتقويمين مع اسم اليوم', function (): void {
    expect(HijriDate::dual('2026-06-16'))->toBe([
        'hijri' => '1 محرم 1448 هـ',
        'gregorian' => '16 يونيو 2026م',
        'weekday' => 'الثلاثاء',
    ]);
});

test('التواريخ بأرقام لاتينية فقط بلا أرقام هندية', function (): void {
    $dual = HijriDate::dual('2025-12-27');

    expect(implode(' ', (array) $dual))->not->toMatch('/[٠-٩]/u')
        ->and($dual['hijri'] ?? null)->toMatch('/^\d{1,2} .+ 1447 هـ$/u')
        ->and($dual['gregorian'] ?? null)->toBe('27 ديسمبر 2025م');
});

test('يعرض التاريخ الهجري لتواريخ معروفة في تقويم أم القرى', function (string $gregorian, string $hijri): void {
    expect(HijriDate::format($gregorian))->toBe($hijri);
})->with([
    'أول رمضان ١٤٤٦' => ['2025-03-01', '1 رمضان 1446 هـ'],
    'آخر رمضان ١٤٤٦ (٢٩ يومًا)' => ['2025-03-29', '29 رمضان 1446 هـ'],
    'أول شوال ١٤٤٦' => ['2025-03-30', '1 شوال 1446 هـ'],
    'يوم النحر ١٤٤٥' => ['2024-06-16', '10 ذو الحجة 1445 هـ'],
]);

test('يقبل كائن التاريخ ويعرضه بتوقيت الرياض', function (): void {
    $lateUtcEvening = CarbonImmutable::parse('2026-06-15 22:30', 'UTC');

    expect(HijriDate::format($lateUtcEvening))->toBe('1 محرم 1448 هـ')
        ->and(HijriDate::weekday($lateUtcEvening))->toBe('الثلاثاء');
});

test('يُرجع null لقيمة فارغة أو غير صالحة', function (mixed $value): void {
    expect(HijriDate::dual($value))->toBeNull()
        ->and(HijriDate::gregorian($value))->toBeNull()
        ->and(HijriDate::countdown($value))->toBeNull();
})->with([
    'null' => [null],
    'نص فارغ' => [''],
    'نص غير تاريخ' => ['ليس تاريخًا'],
]);

test('يحوّل التاريخ الهجري إلى ميلادي', function (int $year, int $month, int $day, string $expected): void {
    expect(HijriDate::toGregorian($year, $month, $day)?->toDateString())->toBe($expected);
})->with([
    'أول محرم ١٤٤٨' => [1448, 1, 1, '2026-06-16'],
    'أول رمضان ١٤٤٦' => [1446, 9, 1, '2025-03-01'],
    '٢٩ رمضان ١٤٤٦' => [1446, 9, 29, '2025-03-29'],
    'أول شوال ١٤٤٦ بعد شهر من ٢٩ يومًا' => [1446, 10, 1, '2025-03-30'],
    'يوم النحر ١٤٤٥' => [1445, 12, 10, '2024-06-16'],
]);

test('يرفض يومًا غير موجود مثل ٣٠ رمضان ١٤٤٦ لأن الشهر ٢٩ يومًا', function (): void {
    expect(HijriDate::toGregorian(1446, 9, 30))->toBeNull();
});

test('يرفض أجزاء تاريخ هجري خارج المدى', function (int $year, int $month, int $day): void {
    expect(HijriDate::toGregorian($year, $month, $day))->toBeNull();
})->with([
    'شهر صفر' => [1448, 0, 1],
    'شهر ١٣' => [1448, 13, 1],
    'يوم صفر' => [1448, 1, 0],
    'يوم ٣١' => [1448, 1, 31],
    'سنة صفر' => [0, 1, 1],
]);

test('التحويل العكسي يطابق كل أيام سنة كاملة عبر حدود الأشهر', function (): void {
    $numeric = new IntlDateFormatter(
        'en_US@calendar=islamic-umalqura;numbers=latn',
        IntlDateFormatter::NONE,
        IntlDateFormatter::NONE,
        'Asia/Riyadh',
        IntlDateFormatter::TRADITIONAL,
        'y-M-d',
    );
    $day = CarbonImmutable::create(2025, 1, 1, 0, 0, 0, 'Asia/Riyadh');

    for ($i = 0; $i < 400; $i++, $day = $day->addDay()) {
        [$year, $month, $date] = array_map(intval(...), explode('-', (string) $numeric->format($day)));

        expect(HijriDate::toGregorian($year, $month, $date)?->toDateString())->toBe($day->toDateString());
    }
});

test('العدّ التنازلي بصياغة عربية صحيحة', function (int $days, string $expected): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-24 12:00', 'Asia/Riyadh'));

    expect(HijriDate::countdown(CarbonImmutable::parse('2026-09-24', 'Asia/Riyadh')->addDays($days)))->toBe($expected);
})->with([
    'مضى' => [-1, 'انتهى الموعد'],
    'اليوم' => [0, 'الموعد اليوم'],
    'يوم واحد' => [1, 'المتبقي: يوم واحد'],
    'يومان' => [2, 'المتبقي: يومان'],
    'ثلاثة' => [3, 'المتبقي: 3 أيام'],
    'عشرة' => [10, 'المتبقي: 10 أيام'],
    'أحد عشر' => [11, 'المتبقي: 11 يومًا'],
    'تسعة وتسعون' => [99, 'المتبقي: 99 يومًا'],
    'مئة' => [100, 'المتبقي: 100 يوم'],
    'مئة وثلاثة' => [103, 'المتبقي: 103 أيام'],
    'مئة وأحد عشر' => [111, 'المتبقي: 111 يومًا'],
]);

test('العدّ التنازلي يحسب اليوم بتوقيت الرياض لا UTC', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-24 22:30', 'UTC'));

    expect(HijriDate::daysUntil('2026-09-25'))->toBe(0)
        ->and(HijriDate::countdown('2026-09-25'))->toBe('الموعد اليوم');
});
