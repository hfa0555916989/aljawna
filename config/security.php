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
    */

    'register' => [
        'max_per_ip_per_hour' => (int) env('REGISTER_MAX_PER_IP_PER_HOUR', 5),
    ],

];
