<?php

declare(strict_types=1);

namespace App\Services;

use App\HealthStatus;
use App\Mail\SystemAlert;
use App\Models\SystemHeartbeat;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * تنبيهات التشغيل بالبريد إلى ALERT_EMAIL وحده (docs/DECISIONS.md): توقف المجدول أو
 * عامل الطوابير، وفشل النسخ الاحتياطي، والارتفاع المفاجئ في الأخطاء.
 *
 * - بلا أي بيانات شخصية: نوع المشكلة ووقتها وعدد فقط.
 * - لا يتكرر نفس التنبيه قبل مهلة التهدئة ما دامت المشكلة قائمة، وزوالها
 *   يعيد ضبطه فيُنبَّه فورًا إن عادت.
 * - يُرسل فورًا لا عبر الطوابير، لأن الطوابير نفسها قد تكون المتوقفة.
 */
class SystemAlerts
{
    public const string SCHEDULER_STALLED = 'scheduler_stalled';

    public const string QUEUE_STALLED = 'queue_stalled';

    public const string BACKUP_FAILED = 'backup_failed';

    public const string ERROR_SPIKE = 'error_spike';

    public function __construct(private SystemHealth $health) {}

    /**
     * يفحص ويرسل ما يلزم، ويعيد مفاتيح التنبيهات المرسلة الآن.
     *
     * @return list<string>
     */
    public function check(): array
    {
        SystemHeartbeat::beat(SystemHeartbeat::MONITOR);

        $problems = $this->currentProblems();

        DB::table('system_alerts')->whereNotIn('key', $problems)->delete();

        $sent = [];

        foreach ($problems as $problem) {
            if ($this->send($problem)) {
                $sent[] = $problem;
            }
        }

        return $sent;
    }

    /**
     * @return list<string>
     */
    public function currentProblems(): array
    {
        $problems = [];
        $schedulerStalled = $this->isStalled($this->health->scheduler()['status'], SystemHeartbeat::SCHEDULER);

        if ($schedulerStalled) {
            $problems[] = self::SCHEDULER_STALLED;
        }

        // نبض الطوابير يرسله المجدول، فتوقّف المجدول يكفي تنبيهًا عن الاثنين.
        if (! $schedulerStalled && $this->isStalled($this->health->queueWorker()['status'], SystemHeartbeat::QUEUE)) {
            $problems[] = self::QUEUE_STALLED;
        }

        if ($this->health->receiptsBackup()['status'] === HealthStatus::Failing) {
            $problems[] = self::BACKUP_FAILED;
        }

        if ($this->health->isErrorSpike()) {
            $problems[] = self::ERROR_SPIKE;
        }

        return $problems;
    }

    /**
     * متوقف: نبضه قديم، أو لم ينبض قط رغم مرور مهلته منذ أول فحص مراقبة
     * (فلا يُنبَّه خطأً في الدقائق الأولى بعد أول نشر).
     */
    private function isStalled(HealthStatus $status, string $name): bool
    {
        if ($status === HealthStatus::Failing) {
            return true;
        }

        if ($status !== HealthStatus::Unknown) {
            return false;
        }

        $minutes = (int) config($name === SystemHeartbeat::SCHEDULER ? 'monitoring.scheduler_stale_minutes' : 'monitoring.queue_stale_minutes');
        $monitor = SystemHeartbeat::named(SystemHeartbeat::MONITOR);

        return $monitor !== null && $monitor->created_at->lt(now()->subMinutes($minutes));
    }

    private function send(string $problem): bool
    {
        $recipient = (string) config('monitoring.alert_email');

        if ($recipient === '') {
            Log::warning('System alert not sent: ALERT_EMAIL is empty.', ['alert' => $problem]);

            return false;
        }

        if (! $this->claim($problem)) {
            return false;
        }

        try {
            Mail::to($recipient)->send(new SystemAlert($problem, $this->detailsFor($problem)));
        } catch (Throwable $exception) {
            DB::table('system_alerts')->where('key', $problem)->delete();
            Log::error('System alert mail failed.', ['alert' => $problem, 'exception' => $exception::class]);

            return false;
        }

        return true;
    }

    /**
     * حجز ذري لإرسال التنبيه: أول مرة، أو بعد انقضاء مهلة التهدئة.
     */
    private function claim(string $problem): bool
    {
        $now = now();

        $inserted = DB::table('system_alerts')->insertOrIgnore([
            'key' => $problem,
            'last_sent_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($inserted === 1) {
            return true;
        }

        return DB::table('system_alerts')
            ->where('key', $problem)
            ->where('last_sent_at', '<=', $now->copy()->subMinutes((int) config('monitoring.alert_cooldown_minutes')))
            ->update(['last_sent_at' => $now, 'updated_at' => $now]) === 1;
    }

    private function detailsFor(string $problem): string
    {
        return match ($problem) {
            self::SCHEDULER_STALLED => $this->health->scheduler()['value'],
            self::QUEUE_STALLED => $this->health->queueWorker()['value'],
            self::BACKUP_FAILED => $this->health->receiptsBackup()['value'],
            self::ERROR_SPIKE => __('health.alerts.error_spike_details', [
                'count' => (int) app(ErrorCounter::class)->countSince((int) config('monitoring.error_spike.window_minutes')),
                'minutes' => (int) config('monitoring.error_spike.window_minutes'),
            ]),
            default => '',
        };
    }
}
