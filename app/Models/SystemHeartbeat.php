<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * آخر نبض لمكوّن تشغيلي (المجدول، عامل الطوابير، النسخ الاحتياطي). بلا بيانات شخصية.
 *
 * @property string $name
 * @property Carbon $last_seen_at
 * @property string|null $status
 * @property Carbon $created_at
 */
#[Fillable(['name', 'last_seen_at', 'status'])]
class SystemHeartbeat extends Model
{
    public const string SCHEDULER = 'scheduler';

    public const string QUEUE = 'queue';

    public const string RECEIPTS_BACKUP = 'receipts_backup';

    /**
     * أول فحص مراقبة: مرجع مهلة السماح قبل التنبيه على مكوّن لم ينبض قط.
     */
    public const string MONITOR = 'monitor';

    public const string BACKUP_SUCCEEDED = 'succeeded';

    public const string BACKUP_FAILED = 'failed';

    public static function beat(string $name, ?string $status = null): void
    {
        $now = now();

        static::query()->upsert(
            [['name' => $name, 'last_seen_at' => $now, 'status' => $status, 'created_at' => $now, 'updated_at' => $now]],
            ['name'],
            ['last_seen_at', 'status', 'updated_at'],
        );
    }

    /**
     * يستدعيه أمر النسخ الاحتياطي للإيصالات (T21) بعد كل تشغيل، ناجحًا أو فاشلًا.
     */
    public static function recordReceiptsBackup(bool $succeeded): void
    {
        static::beat(self::RECEIPTS_BACKUP, $succeeded ? self::BACKUP_SUCCEEDED : self::BACKUP_FAILED);
    }

    public static function named(string $name): ?self
    {
        return static::query()->where('name', $name)->first();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
