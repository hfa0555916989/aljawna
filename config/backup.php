<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| النسخ الاحتياطي المشفّر خارج Laravel Cloud (T21، docs/RUNBOOK.md)
|--------------------------------------------------------------------------
|
| disk: قرص الوجهة (config/filesystems.php)، وهو Cloudflare R2 بحساب منفصل في الإنتاج.
| encryption_key: مفتاح التشفير (base64: ثم 32 بايت). يُحفظ خارج Laravel Cloud وخارج
|                 حساب Cloudflare أيضًا؛ ضياعه يعني استحالة استرجاع أي نسخة.
| receipts.prefix: مجلد نسخ الإيصالات في الوجهة (نسخة مشفّرة لكل إيصال).
| database.prefix: مجلد نسخ قاعدة البيانات اليومية.
| database.excluded_tables: جداول مؤقتة لا تُنسخ (الكاش والجلسات والطوابير ونبض المراقبة)،
|                           وجدول migrations يُعاد بناؤه من الشيفرة عند الاسترجاع.
| database.keep: سياسة الاحتفاظ بالنسخ اليومية: آخر نسخة من كل يوم خلال 14 يومًا، ومن كل
|                أسبوع خلال 8 أسابيع، ومن كل شهر خلال 6 أشهر.
|
| تفعيل كل نوع ومهلة تأخره في config/monitoring.php (صفحة "صحة النظام" وتنبيهاتها).
|
*/

return [

    'disk' => 'backups',

    'encryption_key' => env('BACKUP_ENCRYPTION_KEY'),

    'receipts' => [
        'prefix' => 'receipts',
    ],

    'database' => [
        'prefix' => 'database',
        'excluded_tables' => [
            'migrations',
            'cache',
            'cache_locks',
            'sessions',
            'jobs',
            'job_batches',
            'failed_jobs',
            'system_heartbeats',
            'system_alerts',
        ],
        'keep' => [
            'daily' => (int) env('BACKUP_KEEP_DAILY', 14),
            'weekly' => (int) env('BACKUP_KEEP_WEEKLY', 8),
            'monthly' => (int) env('BACKUP_KEEP_MONTHLY', 6),
        ],
    ],

];
