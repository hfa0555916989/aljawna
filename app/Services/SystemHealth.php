<?php

declare(strict_types=1);

namespace App\Services;

use App\HealthStatus;
use App\Models\SystemHeartbeat;
use App\Support\HijriDate;
use Composer\InstalledVersions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

/**
 * فحوص "صحة النظام" للقراءة فقط (docs/DECISIONS.md). كل فحص يلتقط أخطاءه
 * فلا يُسقط تعطّلُ مكوّنٍ الصفحةَ كلها. لا يقيس مساحة قرص محلي، ولا يعرض
 * أي بيانات شخصية أو أسرار (لا عناوين اتصال ولا كلمات مرور).
 *
 * @phpstan-type Check array{key: string, label: string, status: HealthStatus, value: string}
 */
class SystemHealth
{
    public function __construct(private ErrorCounter $errors) {}

    /**
     * @return list<Check>
     */
    public function checks(): array
    {
        return [
            $this->database(),
            $this->redis(),
            $this->queueWorker(),
            $this->scheduler(),
            $this->receiptsBackup(),
            $this->databaseBackup(),
            $this->receiptsPurge(),
            $this->failedJobs(),
            $this->recentErrors(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function versions(): array
    {
        return [
            'PHP' => PHP_VERSION,
            'Laravel' => app()->version(),
            'Filament' => $this->packageVersion('filament/filament'),
            'Livewire' => $this->packageVersion('livewire/livewire'),
            'PostgreSQL' => $this->databaseVersion(),
            'Redis' => $this->redisVersion(),
        ];
    }

    /**
     * @return Check
     */
    public function database(): array
    {
        try {
            $started = hrtime(true);
            DB::select('select 1');
            $milliseconds = (int) round((hrtime(true) - $started) / 1_000_000);

            return $this->check('database', HealthStatus::Ok, __('health.values.latency', ['ms' => $milliseconds]));
        } catch (Throwable) {
            return $this->check('database', HealthStatus::Failing, __('health.values.unreachable'));
        }
    }

    /**
     * @return Check
     */
    public function redis(): array
    {
        try {
            $started = hrtime(true);
            Redis::connection()->ping();
            $milliseconds = (int) round((hrtime(true) - $started) / 1_000_000);

            return $this->check('redis', HealthStatus::Ok, __('health.values.latency', ['ms' => $milliseconds]));
        } catch (Throwable) {
            return $this->check('redis', HealthStatus::Failing, __('health.values.unreachable'));
        }
    }

    /**
     * @return Check
     */
    public function queueWorker(): array
    {
        return $this->heartbeatCheck('queue', SystemHeartbeat::QUEUE, (int) config('monitoring.queue_stale_minutes'));
    }

    /**
     * @return Check
     */
    public function scheduler(): array
    {
        return $this->heartbeatCheck('scheduler', SystemHeartbeat::SCHEDULER, (int) config('monitoring.scheduler_stale_minutes'));
    }

    /**
     * @return Check
     */
    public function receiptsBackup(): array
    {
        return $this->backupCheck('backup', SystemHeartbeat::RECEIPTS_BACKUP, 'monitoring.backup');
    }

    /**
     * @return Check
     */
    public function databaseBackup(): array
    {
        return $this->backupCheck('database_backup', SystemHeartbeat::DATABASE_BACKUP, 'monitoring.database_backup');
    }

    /**
     * آخر تشغيل لحذف صور الإيصالات المنتهية: فاشل إن تعذّر حذف أي صورة (تبقى في القاعدة ويُعاد
     * المحاولة يوميًا). لا مهلة تأخّر: توقّف المجدول نفسه له تنبيهه.
     *
     * @return Check
     */
    public function receiptsPurge(): array
    {
        $heartbeat = $this->heartbeat(SystemHeartbeat::RECEIPTS_PURGE);

        if ($heartbeat === null) {
            return $this->check('receipts_purge', HealthStatus::Unknown, __('health.values.never'));
        }

        $failed = $heartbeat->status !== SystemHeartbeat::BACKUP_SUCCEEDED;

        return $this->check(
            'receipts_purge',
            $failed ? HealthStatus::Failing : HealthStatus::Ok,
            __($failed ? 'health.values.backup_failed' : 'health.values.backup_succeeded', ['time' => $this->formatTime($heartbeat->last_seen_at)]),
        );
    }

    /**
     * @return Check
     */
    public function failedJobs(): array
    {
        try {
            $count = DB::table((string) config('queue.failed.table', 'failed_jobs'))->count();

            return $this->check('failed_jobs', $count === 0 ? HealthStatus::Ok : HealthStatus::Failing, (string) $count);
        } catch (Throwable) {
            return $this->check('failed_jobs', HealthStatus::Unknown, __('health.values.unavailable'));
        }
    }

    /**
     * @return Check
     */
    public function recentErrors(): array
    {
        $count = $this->errors->lastDay();

        if ($count === null) {
            return $this->check('errors', HealthStatus::Unknown, __('health.values.unavailable'));
        }

        return $this->check('errors', $this->isErrorSpike() ? HealthStatus::Failing : HealthStatus::Ok, (string) $count);
    }

    public function isErrorSpike(): bool
    {
        $recent = $this->errors->countSince((int) config('monitoring.error_spike.window_minutes'));

        return $recent !== null && $recent >= (int) config('monitoring.error_spike.threshold');
    }

    /**
     * @return Check
     */
    private function heartbeatCheck(string $key, string $name, int $staleMinutes): array
    {
        $heartbeat = $this->heartbeat($name);

        if ($heartbeat === null) {
            return $this->check($key, HealthStatus::Unknown, __('health.values.never'));
        }

        $isStale = $heartbeat->last_seen_at->lt(now()->subMinutes($staleMinutes));

        return $this->check(
            $key,
            $isStale ? HealthStatus::Failing : HealthStatus::Ok,
            __('health.values.last_seen', ['time' => $this->formatTime($heartbeat->last_seen_at)]),
        );
    }

    /**
     * نسخة احتياطية: "غير مُعدّ" حتى تفعيلها، ثم فاشلة إن فشل آخر تشغيل أو تأخّر أكثر من مهلتها.
     *
     * @return Check
     */
    private function backupCheck(string $key, string $name, string $configKey): array
    {
        if (! config($configKey.'.enabled')) {
            return $this->check($key, HealthStatus::Unconfigured, __('health.values.unconfigured'));
        }

        $heartbeat = $this->heartbeat($name);

        if ($heartbeat === null) {
            return $this->check($key, HealthStatus::Unknown, __('health.values.never'));
        }

        $isStale = $heartbeat->last_seen_at->lt(now()->subHours((int) config($configKey.'.max_age_hours')));
        $failed = $heartbeat->status !== SystemHeartbeat::BACKUP_SUCCEEDED;

        return $this->check(
            $key,
            $failed || $isStale ? HealthStatus::Failing : HealthStatus::Ok,
            __($failed ? 'health.values.backup_failed' : 'health.values.backup_succeeded', ['time' => $this->formatTime($heartbeat->last_seen_at)]),
        );
    }

    private function heartbeat(string $name): ?SystemHeartbeat
    {
        try {
            return SystemHeartbeat::named($name);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return Check
     */
    private function check(string $key, HealthStatus $status, string $value): array
    {
        return [
            'key' => $key,
            'label' => __('health.checks.'.$key),
            'status' => $status,
            'value' => $value,
        ];
    }

    private function formatTime(Carbon $time): string
    {
        $local = $time->copy()->timezone('Asia/Riyadh');

        return $local->format('H:i').' — '.HijriDate::format($local).' / '.HijriDate::gregorian($local).' ('.$local->diffForHumans().')';
    }

    private function packageVersion(string $package): string
    {
        return InstalledVersions::getPrettyVersion($package) ?? '—';
    }

    private function databaseVersion(): string
    {
        try {
            return (string) (DB::selectOne('show server_version')->server_version ?? '—');
        } catch (Throwable) {
            return '—';
        }
    }

    private function redisVersion(): string
    {
        try {
            /** @var array<string, mixed> $info */
            $info = Redis::connection()->command('info', ['server']);
            $version = $info['redis_version'] ?? $info['Server']['redis_version'] ?? null;

            return is_string($version) ? $version : '—';
        } catch (Throwable) {
            return '—';
        }
    }
}
