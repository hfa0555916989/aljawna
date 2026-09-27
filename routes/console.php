<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('recovery:expire')->hourly();

// مراقبة التشغيل (docs/DECISIONS.md): نبض المجدول والطوابير كل دقيقة، والفحص والتنبيه كل 5 دقائق.
Schedule::command('monitor:heartbeat')->everyMinute()->withoutOverlapping()->onOneServer();
Schedule::command('monitor:check')->everyFiveMinutes()->withoutOverlapping()->onOneServer();
