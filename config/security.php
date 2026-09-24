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
    |
    */

    'transfers' => [
        'daily_limit' => (int) env('TRANSFERS_DAILY_LIMIT', 10),
    ],

    'receipts' => [
        'disk' => env('RECEIPTS_DISK', 'receipts'),
        'link_minutes' => (int) env('RECEIPT_LINK_MINUTES', 10),
        'max_pixels' => (int) env('RECEIPT_MAX_PIXELS', 20000000),
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

];
