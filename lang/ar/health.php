<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| صحة النظام وتنبيهات التشغيل (docs/DECISIONS.md)
|--------------------------------------------------------------------------
*/

return [

    'navigation' => 'صحة النظام',
    'intro' => 'قراءة فقط. لا يمكن تشغيل أي أمر على الخادم من هذه الصفحة.',
    'versions' => 'الإصدارات',
    'refreshed_at' => 'آخر تحديث: :time',

    'checks' => [
        'database' => 'قاعدة البيانات',
        'redis' => 'Redis',
        'queue' => 'نبض عامل الطوابير',
        'scheduler' => 'آخر تشغيل للمجدول',
        'backup' => 'آخر نسخة احتياطية للإيصالات',
        'database_backup' => 'آخر نسخة احتياطية لقاعدة البيانات',
        'receipts_purge' => 'آخر حذف لصور الإيصالات المنتهية',
        'failed_jobs' => 'المهام الفاشلة',
        'errors' => 'الأخطاء خلال آخر 24 ساعة',
    ],

    'status' => [
        'ok' => 'سليم',
        'failing' => 'يحتاج انتباهًا',
        'unknown' => 'غير معروف',
        'unconfigured' => 'غير مُعدّ',
    ],

    'values' => [
        'latency' => 'متصل (:ms ms)',
        'unreachable' => 'تعذّر الاتصال',
        'unavailable' => 'تعذّرت القراءة',
        'never' => 'لم يُسجَّل بعد',
        'unconfigured' => 'غير مُعدّ',
        'last_seen' => ':time',
        'backup_succeeded' => 'نجحت: :time',
        'backup_failed' => 'فشلت: :time',
    ],

    'alerts' => [
        'subject' => '[تنبيه] :app — :title',
        'time' => 'وقت الفحص (الرياض): :time',
        'link' => 'للتفاصيل افتح صفحة "صحة النظام" من لوحة الإدارة.',
        'footer' => 'رسالة آلية من نظام المراقبة، ولا تحتوي أي بيانات شخصية. لن يتكرر هذا التنبيه قبل انقضاء مهلة التهدئة ما دامت المشكلة قائمة.',
        'error_spike_details' => ':count خطأ خلال آخر :minutes دقيقة.',

        'scheduler_stalled' => [
            'title' => 'المجدول متوقف',
            'action' => 'تحقق من تفعيل المجدول (Scheduler) في بيئة Laravel Cloud.',
        ],
        'queue_stalled' => [
            'title' => 'عامل الطوابير متوقف',
            'action' => 'تحقق من عامل الطوابير (Queue worker) وRedis في بيئة Laravel Cloud.',
        ],
        'backup_failed' => [
            'title' => 'فشل النسخ الاحتياطي للإيصالات أو تأخّر',
            'action' => 'راجع سجل الأمر backup:receipts في Laravel Cloud ووجهة النسخ في Cloudflare R2.',
        ],
        'database_backup_failed' => [
            'title' => 'فشل النسخ الاحتياطي لقاعدة البيانات أو تأخّر',
            'action' => 'راجع سجل الأمر backup:database في Laravel Cloud ووجهة النسخ في Cloudflare R2.',
        ],
        'receipts_purge_failed' => [
            'title' => 'تعذّر حذف صور إيصالات انتهت مدة الاحتفاظ بها',
            'action' => 'بقيت الصور ومساراتها كما هي ويُعاد المحاولة يوميًا. راجع سجل الأمر receipts:purge-expired في Laravel Cloud وحالة الـ bucket.',
        ],
        'test' => [
            'title' => 'تنبيه تجريبي',
            'action' => 'لا يلزم أي إجراء: وصول هذه الرسالة يعني أن تنبيهات التشغيل تعمل.',
            'details' => 'أُرسل يدويًا بالأمر php artisan alerts:test (ناقل البريد: :mailer).',
        ],
        'error_spike' => [
            'title' => 'ارتفاع مفاجئ في الأخطاء',
            'action' => 'راجع سجلات التطبيق في Laravel Cloud.',
        ],
    ],

];
