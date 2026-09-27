<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SystemAlerts;
use Illuminate\Console\Command;

/**
 * فحص المراقبة وإرسال التنبيهات اللازمة إلى ALERT_EMAIL. يشغّله المجدول، ويشغّله
 * أيضًا RunMonitoringWatchdog من طلبات الويب لاكتشاف توقف المجدول نفسه.
 * للقراءة والتنبيه فقط، ولا يعدّل أي بيانات تشغيلية.
 */
class MonitorCheckCommand extends Command
{
    protected $signature = 'monitor:check';

    protected $description = 'فحص صحة التشغيل وإرسال تنبيهات البريد عند الحاجة';

    public function handle(SystemAlerts $alerts): int
    {
        $sent = $alerts->check();

        $this->info($sent === [] ? 'لا تنبيهات جديدة.' : 'أُرسلت التنبيهات: '.implode('، ', $sent));

        return self::SUCCESS;
    }
}
