<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\RecordQueueHeartbeat;
use App\Models\SystemHeartbeat;
use Illuminate\Console\Command;

/**
 * نبض المجدول ونبض عامل الطوابير، كل دقيقة من المجدول (routes/console.php).
 */
class MonitorHeartbeatCommand extends Command
{
    protected $signature = 'monitor:heartbeat';

    protected $description = 'تسجيل نبض المجدول وإرسال نبض إلى عامل الطوابير';

    public function handle(): int
    {
        SystemHeartbeat::beat(SystemHeartbeat::SCHEDULER);

        RecordQueueHeartbeat::dispatch();

        return self::SUCCESS;
    }
}
