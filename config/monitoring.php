<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| مراقبة التشغيل: صفحة "صحة النظام" وتنبيهات البريد (docs/DECISIONS.md)
|--------------------------------------------------------------------------
|
| alert_email: العنوان الوحيد الذي تصله التنبيهات (مسؤول الدعم الفني). فارغ = لا تنبيهات.
| alert_cooldown_minutes: لا يتكرر نفس التنبيه قبل انقضاء هذه المدة ما دامت المشكلة قائمة.
| scheduler_stale_minutes / queue_stale_minutes: عمر آخر نبض قبل اعتبار المكوّن متوقفًا.
| error_spike: عدد الأخطاء المسجَّلة خلال window_minutes الذي يُعد ارتفاعًا مفاجئًا.
| backup: نسخ الإيصالات الاحتياطي. "غير مُعدّ" حتى تفعيله في T21، ثم يُنبَّه عند فشل
|         آخر نسخة أو تأخرها أكثر من max_age_hours.
| watchdog_seconds: أقل فاصل لفحص المراقبة من طلبات الويب، لاكتشاف توقف المجدول نفسه.
|
*/

return [

    'alert_email' => env('ALERT_EMAIL'),

    'alert_cooldown_minutes' => (int) env('ALERT_COOLDOWN_MINUTES', 60),

    'scheduler_stale_minutes' => (int) env('MONITOR_SCHEDULER_STALE_MINUTES', 5),

    'queue_stale_minutes' => (int) env('MONITOR_QUEUE_STALE_MINUTES', 5),

    'error_spike' => [
        'threshold' => (int) env('ALERT_ERROR_SPIKE_THRESHOLD', 50),
        'window_minutes' => (int) env('ALERT_ERROR_SPIKE_WINDOW_MINUTES', 15),
    ],

    'backup' => [
        'enabled' => (bool) env('RECEIPTS_BACKUP_ENABLED', false),
        'max_age_hours' => (int) env('RECEIPTS_BACKUP_MAX_AGE_HOURS', 26),
    ],

    'watchdog_seconds' => (int) env('MONITOR_WATCHDOG_SECONDS', 300),

];
