<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        // إيصالات الحوالات: قرص خاص لا يُخدم مباشرة، وتُعرض عبر مسار موقّع بعد فحص Policy (docs/SPEC.md §12.6).
        // القرص محلي افتراضيًا، وقابل للتبديل إلى bucket متوافق مع S3 عبر RECEIPTS_FILESYSTEM_DRIVER=s3
        // (docs/DEPLOY-NOTES.md)، فتُقرأ بيانات الاتصال من RECEIPTS_AWS_* أو من AWS_* العامة إن لم تُحدَّد.
        'receipts' => match (env('RECEIPTS_FILESYSTEM_DRIVER', 'local')) {
            's3' => [
                'driver' => 's3',
                'key' => env('RECEIPTS_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
                'secret' => env('RECEIPTS_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
                'region' => env('RECEIPTS_AWS_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
                'bucket' => env('RECEIPTS_AWS_BUCKET', env('AWS_BUCKET')),
                'url' => env('RECEIPTS_AWS_URL', env('AWS_URL')),
                'endpoint' => env('RECEIPTS_AWS_ENDPOINT', env('AWS_ENDPOINT')),
                'use_path_style_endpoint' => (bool) env('RECEIPTS_AWS_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
                'root' => env('RECEIPTS_AWS_ROOT', 'receipts'),
                'visibility' => 'private',
                'throw' => true,
                'report' => false,
            ],
            default => [
                'driver' => 'local',
                'root' => storage_path('app/private/receipts'),
                'visibility' => 'private',
                'serve' => false,
                'throw' => true,
                'report' => false,
            ],
        },

        // صور الهوية (الشعار والأيقونة) وصور منشئ الصفحات (docs/SPEC.md FR-49, FR-50، §12.13).
        // قرص عام محلي افتراضيًا، وقابل للتبديل إلى bucket متوافق مع S3 عبر PUBLIC_FILESYSTEM_DRIVER=s3.
        'public' => match (env('PUBLIC_FILESYSTEM_DRIVER', 'local')) {
            's3' => [
                'driver' => 's3',
                'key' => env('PUBLIC_AWS_ACCESS_KEY_ID', env('AWS_ACCESS_KEY_ID')),
                'secret' => env('PUBLIC_AWS_SECRET_ACCESS_KEY', env('AWS_SECRET_ACCESS_KEY')),
                'region' => env('PUBLIC_AWS_DEFAULT_REGION', env('AWS_DEFAULT_REGION')),
                'bucket' => env('PUBLIC_AWS_BUCKET', env('AWS_BUCKET')),
                'url' => env('PUBLIC_AWS_URL', env('AWS_URL')),
                'endpoint' => env('PUBLIC_AWS_ENDPOINT', env('AWS_ENDPOINT')),
                'use_path_style_endpoint' => (bool) env('PUBLIC_AWS_USE_PATH_STYLE_ENDPOINT', env('AWS_USE_PATH_STYLE_ENDPOINT', false)),
                'root' => env('PUBLIC_AWS_ROOT', 'public'),
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ],
            default => [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ],
        },

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
