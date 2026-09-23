<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | حساب المدير الأول
    |--------------------------------------------------------------------------
    |
    | يستخدمه database\seeders\AdminUserSeeder لإنشاء المدير الأول من قيم
    | البيئة دون أي قيم ثابتة في الكود (tasks/T01-users-base.md).
    |
    */

    'name' => env('ADMIN_NAME'),

    'phone' => env('ADMIN_PHONE'),

    'password' => env('ADMIN_PASSWORD'),

];
