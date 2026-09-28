<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | حدود الدخول (docs/SPEC.md §3, §12.2)
    |--------------------------------------------------------------------------
    |
    | بعد max_attempts محاولات فاشلة خلال decay_minutes (لكل جوال ولكل IP)
    | يُقفل الدخول lockout_minutes، وتتضاعف المدة مع كل قفل متكرر خلال
    | strikes_reset_hours حتى lockout_max_minutes.
    |
    */

    'login' => [
        'max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
        'decay_minutes' => (int) env('LOGIN_DECAY_MINUTES', 15),
        'lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
        'lockout_max_minutes' => (int) env('LOGIN_LOCKOUT_MAX_MINUTES', 1440),
        'strikes_reset_hours' => (int) env('LOGIN_STRIKES_RESET_HOURS', 24),
    ],

    /*
    |--------------------------------------------------------------------------
    | رؤوس الأمان (docs/SPEC.md §12.1, §12.13)
    |--------------------------------------------------------------------------
    |
    | hsts_max_age: مدة Strict-Transport-Security بالثواني على الطلبات الآمنة
    | خارج البيئة المحلية (0 يعطّله). includeSubDomains اختياري لأنه يُلزم كل
    | النطاقات الفرعية بـ HTTPS ويصعب التراجع عنه. preload لا يُضاف من التطبيق.
    | csp_report_only: يرسل السياسة للمراقبة فقط دون فرضها، للطوارئ لا للدوام.
    | القيمة في التطبيق تتقدّم على إعدادات "Edge network" في Laravel Cloud.
    |
    */

    'headers' => [
        'hsts_max_age' => (int) env('HSTS_MAX_AGE', 31536000),
        'hsts_include_subdomains' => (bool) env('HSTS_INCLUDE_SUBDOMAINS', false),
        'csp_report_only' => (bool) env('CSP_REPORT_ONLY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | التحقق بخطوتين (docs/DECISIONS.md)
    |--------------------------------------------------------------------------
    |
    | setup_link_minutes: صلاحية رابط الإعداد من php artisan admin:reset-2fa.
    | recovery_regeneration_max_attempts: رموز TOTP خاطئة في صفحة تجديد رموز
    | الاسترداد لكل حساب خلال recovery_regeneration_decay_minutes قبل إيقافها.
    |
    */

    'two_factor' => [
        'setup_link_minutes' => (int) env('TWO_FACTOR_SETUP_LINK_MINUTES', 30),
        'recovery_regeneration_max_attempts' => 5,
        'recovery_regeneration_decay_minutes' => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | حدود التسجيل (docs/SPEC.md §12.3)
    |--------------------------------------------------------------------------
    |
    | كل محاولة تسجيل من الـ IP تُحتسب، ناجحة كانت أو فاشلة.
    |
    */

    'register' => [
        'max_per_ip_per_hour' => (int) env('REGISTER_MAX_PER_IP_PER_HOUR', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | نموذج "اتصل بنا" (docs/SPEC.md §9 contact_messages، FR-55)
    |--------------------------------------------------------------------------
    |
    | كل محاولة إرسال من الـ IP تُحتسب، ناجحة كانت أو فاشلة.
    |
    */

    'contact' => [
        'max_per_ip_per_hour' => (int) env('CONTACT_MAX_PER_IP_PER_HOUR', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | تنبيه تغيير الحسابات البنكية (docs/SPEC.md §12.10)
    |--------------------------------------------------------------------------
    |
    | يظهر للمدير في لوحته كل تغيير لحساب بنكي لمستفيد خلال آخر alert_days يومًا.
    |
    */

    'bank_account_changes' => [
        'alert_days' => (int) env('BANK_CHANGE_ALERT_DAYS', 14),
    ],

    /*
    |--------------------------------------------------------------------------
    | الحوالات والإيصالات (docs/SPEC.md §12.3, §12.6)
    |--------------------------------------------------------------------------
    |
    | daily_limit: أقصى عدد حوالات يرفعها المبادر في اليوم الواحد بتوقيت الرياض.
    | receipts.disk: قرص خاص (قابل للتبديل إلى تخزين كائنات خاص).
    | receipts.link_minutes: مدة صلاحية رابط عرض الإيصال الموقّع.
    | receipts.max_pixels: أقصى أبعاد للصورة (العرض × الارتفاع) قبل فك ترميزها،
    | حماية للذاكرة من الصور المضغوطة المفخخة.
    | receipts.retention_months: تُحذف صور إيصالات المبادرة بعد إقفالها بهذه المدة، من التخزين
    | ومن النسخ الاحتياطية (php artisan receipts:purge-expired)، وتبقى بيانات الحوالة دائمًا.
    |
    */

    'transfers' => [
        'daily_limit' => (int) env('TRANSFERS_DAILY_LIMIT', 10),
    ],

    'receipts' => [
        'disk' => env('RECEIPTS_DISK', 'receipts'),
        'link_minutes' => (int) env('RECEIPT_LINK_MINUTES', 10),
        'max_pixels' => (int) env('RECEIPT_MAX_PIXELS', 20000000),
        'retention_months' => (int) env('RECEIPTS_RETENTION_MONTHS', 6),
    ],

    /*
    |--------------------------------------------------------------------------
    | صور الهوية (docs/SPEC.md FR-49, §12.13)
    |--------------------------------------------------------------------------
    |
    | disk: قرص عام لأن الشعار يظهر للجميع، بخلاف قرص الإيصالات الخاص.
    | max_pixels أصغر من الإيصالات لأن الشعار صورة صغيرة نسبيًا.
    |
    */

    'branding' => [
        'disk' => env('BRANDING_DISK', 'public'),
        'max_kilobytes' => (int) env('BRANDING_IMAGE_MAX_KILOBYTES', 2048),
        'max_pixels' => (int) env('BRANDING_IMAGE_MAX_PIXELS', 4000000),
    ],

    /*
    |--------------------------------------------------------------------------
    | مؤشرات الحوالات (docs/SPEC.md §7)
    |--------------------------------------------------------------------------
    |
    | cache_seconds: مدة تخزين مؤشرات StatsService مؤقتًا، وتُبطَل فورًا عند
    | إضافة حوالة أو تعديلها (App\Models\Transfer::booted())، فهذه المدة سقفٌ
    | أقصى فقط لبقية الحالات.
    |
    */

    'stats' => [
        'cache_seconds' => (int) env('STATS_CACHE_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | منشئ الصفحات (docs/SPEC.md FR-50..56, §12.13)
    |--------------------------------------------------------------------------
    |
    | max_pages: أقصى عدد للصفحات كلها ومنها الرئيسية.
    | max_blocks: أقصى عدد كتل في الصفحة الواحدة.
    | max_kilobytes: أقصى حجم لمحتوى الصفحة (الكتل بصيغة JSON).
    | max_menu_items: أقصى عدد عناصر في كل قائمة (الرأس أو التذييل).
    | reserved_slugs: مسارات لا تأخذها صفحة، إضافة إلى أول مقطع من كل مسار مسجّل
    | في النظام (App\Support\ReservedSlugs)، فلا تحجب صفحة مسارًا نظاميًا.
    |
    */

    'pages' => [
        'max_pages' => (int) env('PAGES_MAX_PAGES', 50),
        'max_blocks' => (int) env('PAGES_MAX_BLOCKS', 30),
        'max_kilobytes' => (int) env('PAGES_MAX_KILOBYTES', 100),
        'max_menu_items' => (int) env('PAGES_MAX_MENU_ITEMS', 8),
        'reserved_slugs' => [
            'admin', 'login', 'logout', 'register', 'forgot-password', 'reset', 'beneficiaries',
            'dashboard', 'my-transfers', 'transfers', 'receipts', 'join', 'contact', 'up',
            'brand', 'storage', 'livewire', 'filament', 'build', 'sitemap', 'robots', 'favicon',
            'api', 'home', 'index', 'page', 'pages', 'preview',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | استعادة كلمة المرور (docs/SPEC.md §4, §12.3)
    |--------------------------------------------------------------------------
    |
    | interval_minutes: الفاصل بين طلبين لنفس الرقم.
    | request_hours: يُغلق الطلب تلقائيًا بعدها.
    | claim_minutes: حجز الاستلام لمشرف واحد.
    | link_minutes: صلاحية رابط التعيين.
    | max_per_ip_per_hour: حد الطلبات لكل IP. المواصفة تذكر الحد بلا رقم؛
    | الافتراضي يطابق حد التسجيل إلى أن يُحسم.
    |
    */

    'recovery' => [
        'interval_minutes' => 10,
        'request_hours' => 24,
        'claim_minutes' => 15,
        'link_minutes' => 30,
        'max_per_ip_per_hour' => (int) env('RECOVERY_MAX_PER_IP_PER_HOUR', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | الحسابات المشبوهة (docs/SPEC.md §12.7)
    |--------------------------------------------------------------------------
    |
    | يُعلَّم حساب المبادر مشبوهًا إن بلغ threshold خلال آخر hours ساعة:
    | failed_logins: محاولات دخول فاشلة برقمه.
    | registrations_per_ip: حسابات مسجّلة من IP تسجيله نفسه.
    | recovery_requests: طلبات استعادة لحسابه.
    |
    */

    'suspicious' => [
        'failed_logins' => [
            'threshold' => (int) env('SUSPICIOUS_FAILED_LOGINS', 10),
            'hours' => (int) env('SUSPICIOUS_FAILED_LOGINS_HOURS', 24),
        ],
        'registrations_per_ip' => [
            'threshold' => (int) env('SUSPICIOUS_REGISTRATIONS_PER_IP', 3),
            'hours' => (int) env('SUSPICIOUS_REGISTRATIONS_PER_IP_HOURS', 24),
        ],
        'recovery_requests' => [
            'threshold' => (int) env('SUSPICIOUS_RECOVERY_REQUESTS', 3),
            'hours' => (int) env('SUSPICIOUS_RECOVERY_REQUESTS_HOURS', 168),
        ],
    ],

];
