<?php

declare(strict_types=1);

use App\Support\BackupRetentionPolicy;
use Carbon\CarbonImmutable;

/*
|--------------------------------------------------------------------------
| سياسة الاحتفاظ بنسخ قاعدة البيانات: 14 يومًا / 8 أسابيع / 6 أشهر (T21)
|--------------------------------------------------------------------------
*/

/**
 * نسخة يومية لكل يوم خلال $days يومًا حتى $now.
 *
 * @return array<string, CarbonImmutable>
 */
function dailyBackups(CarbonImmutable $now, int $days): array
{
    $backups = [];

    for ($day = 0; $day < $days; $day++) {
        $createdAt = $now->subDays($day)->setTime(0, 0);
        $backups['database/'.$createdAt->format('Y-m-d\THis\Z').'.ajdb'] = $createdAt;
    }

    return $backups;
}

test('سنة من النسخ اليومية تنتهي بآخر 14 يومًا وأسبوعيات 8 أسابيع وشهريات 6 أشهر', function (): void {
    $now = CarbonImmutable::parse('2026-09-28 03:00', 'UTC');
    $backups = dailyBackups($now, 365);

    $kept = collect((new BackupRetentionPolicy(14, 8, 6))->keep($backups, $now))
        ->map(fn (string $name): CarbonImmutable => $backups[$name]);

    $daily = $kept->filter(fn (CarbonImmutable $at): bool => $at->gte($now->subDays(14)));
    $older = $kept->filter(fn (CarbonImmutable $at): bool => $at->lt($now->subDays(14)));

    expect($daily)->toHaveCount(14)
        ->and($kept->min())->toBeGreaterThanOrEqual($now->subMonths(6))
        ->and($older->filter(fn (CarbonImmutable $at): bool => $at->gte($now->subWeeks(8))))->not->toBeEmpty()
        ->and($kept->count())->toBeLessThanOrEqual(14 + 8 + 6)
        ->and($kept->count())->toBeLessThan(count($backups));
});

test('من نسخ اليوم الواحد تبقى الأحدث فقط خارج نافذة اليوم', function (): void {
    $now = CarbonImmutable::parse('2026-09-28 12:00', 'UTC');
    $morning = $now->subDays(3)->setTime(1, 0);
    $evening = $now->subDays(3)->setTime(23, 0);

    $kept = (new BackupRetentionPolicy(14, 8, 6))->keep([
        'morning' => $morning,
        'evening' => $evening,
    ], $now);

    expect($kept)->toBe(['evening']);
});

test('أحدث نسخة تبقى دائمًا ولو كانت أقدم من كل النوافذ', function (): void {
    $now = CarbonImmutable::parse('2026-09-28', 'UTC');

    $kept = (new BackupRetentionPolicy(14, 8, 6))->keep([
        'old' => $now->subYears(2),
        'older' => $now->subYears(3),
    ], $now);

    expect($kept)->toBe(['old']);
});

test('لا نسخ: لا شيء يبقى ولا خطأ', function (): void {
    expect((new BackupRetentionPolicy(14, 8, 6))->keep([], CarbonImmutable::now()))->toBe([]);
});
