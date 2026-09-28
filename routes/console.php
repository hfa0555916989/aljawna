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

// النسخ الاحتياطي المشفّر خارج Laravel Cloud (T21، docs/RUNBOOK.md): الإيصالات الجديدة كل ساعة،
// وقاعدة البيانات يوميًا مع سياسة الاحتفاظ. لا يعملان إلا بعد تفعيلهما من متغيرات البيئة.
Schedule::command('backup:receipts')->hourlyAt(15)->withoutOverlapping()->onOneServer();
Schedule::command('backup:database')->dailyAt('03:00')->timezone('Asia/Riyadh')->withoutOverlapping()->onOneServer();

// حذف صور إيصالات المبادرات المقفلة منذ أكثر من 6 أشهر، من التخزين ومن النسخ (T21).
Schedule::command('receipts:purge-expired')->dailyAt('04:30')->timezone('Asia/Riyadh')->withoutOverlapping()->onOneServer();
