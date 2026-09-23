<?php

declare(strict_types=1);

return [

    'updated' => 'تم تحديث الصلاحيات.',

    'errors' => [
        'unauthorized' => 'لا تملك صلاحية إدارة المشرفين.',
        'self' => 'لا يمكنك تعديل صلاحياتك بنفسك.',
        'admin' => 'صلاحيات المدير ثابتة ولا تُعدَّل ولا تُسحب.',
        'not_supervisor' => 'تُمنح الصلاحيات للمشرفين فقط.',
        'grant_unowned' => 'لا يمكنك منح صلاحية لا تملكها: :permissions',
        'unknown' => 'صلاحية غير معروفة: :permissions',
        'requires' => 'الصلاحية :permission تتطلب منح :required معها.',
        'sensitive_admin_only' => 'الصلاحيات الحساسة يمنحها المدير وحده: :permissions',
        'last_admin' => 'يجب أن يبقى مدير فعّال واحد على الأقل.',
        'demote_admin_only' => 'تخفيض المدير من حق مدير آخر فقط.',
        'demote_self' => 'لا يمكنك تخفيض دورك بنفسك.',
        'demote_not_admin' => 'هذا الحساب ليس مديرًا.',
    ],

];
