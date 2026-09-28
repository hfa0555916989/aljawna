<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * سياسة الاحتفاظ بنسخ قاعدة البيانات (T21): آخر نسخة من كل يوم خلال $daily يومًا،
 * ومن كل أسبوع خلال $weekly أسبوعًا، ومن كل شهر خلال $monthly شهرًا. أحدث نسخة
 * تبقى دائمًا مهما كان عمرها، فلا تُحذف آخر نسخة سليمة إن توقف النسخ طويلًا.
 */
final class BackupRetentionPolicy
{
    public function __construct(
        private readonly int $daily,
        private readonly int $weekly,
        private readonly int $monthly,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('backup.database.keep.daily'),
            (int) config('backup.database.keep.weekly'),
            (int) config('backup.database.keep.monthly'),
        );
    }

    /**
     * أسماء النسخ التي تبقى.
     *
     * @param  array<string, CarbonInterface>  $backups  اسم النسخة => وقت إنشائها
     * @return list<string>
     */
    public function keep(array $backups, CarbonInterface $now): array
    {
        arsort($backups);

        $keep = [];
        $windows = [
            [$now->copy()->subDays($this->daily), 'Y-m-d'],
            [$now->copy()->subWeeks($this->weekly), 'o-W'],
            [$now->copy()->subMonths($this->monthly), 'Y-m'],
        ];

        foreach ($windows as [$since, $bucketFormat]) {
            $seenBuckets = [];

            foreach ($backups as $name => $createdAt) {
                if ($createdAt->lt($since)) {
                    continue;
                }

                $bucket = $createdAt->format($bucketFormat);

                if (! isset($seenBuckets[$bucket])) {
                    $seenBuckets[$bucket] = true;
                    $keep[$name] = true;
                }
            }
        }

        if ($backups !== []) {
            $keep[(string) array_key_first($backups)] = true;
        }

        return array_values(array_filter(array_keys($backups), fn (string $name): bool => isset($keep[$name])));
    }
}
