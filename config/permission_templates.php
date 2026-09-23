<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| قوالب الأدوار الجاهزة للمشرفين (docs/SPEC.md §2)
|--------------------------------------------------------------------------
|
| القالب مجرد تحديد أولي للصلاحيات عند الدعوة أو التعديل، ويستطيع المدير
| تعديل أي صلاحية بعده بشكل فردي. لا يُخزَّن القالب على المشرف.
|
*/

return [

    'transfers_reviewer' => [
        'label' => 'مراجع الحوالات',
        'permissions' => ['stats.view', 'transfers.view', 'transfers.review'],
    ],

    'support_recovery' => [
        'label' => 'مسؤول الدعم والاستعادة',
        'permissions' => ['recovery.handle', 'users.view'],
    ],

    'follow_up' => [
        'label' => 'مشرف متابعة',
        'permissions' => ['stats.view', 'transfers.view', 'users.view', 'converter.use'],
    ],

    'security' => [
        'label' => 'مشرف أمان',
        'permissions' => ['security.view', 'users.suspend'],
    ],

    'beneficiaries' => [
        'label' => 'مسؤول المستفيدين',
        'permissions' => ['beneficiaries.manage', 'stats.view', 'transfers.view'],
    ],

];
