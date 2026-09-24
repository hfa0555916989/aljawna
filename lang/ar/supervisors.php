<?php

declare(strict_types=1);

return [

    'navigation' => 'المشرفون',
    'empty' => 'لا مشرفون بعد',
    'model' => 'مشرف',
    'plural' => 'المشرفون',
    'name' => 'الاسم',
    'phone' => 'الجوال',
    'active' => 'مفعّل',
    'show_contact' => 'إظهار الرقم عند الاستعادة',
    'permissions' => 'الصلاحيات',
    'deactivate' => 'تعطيل',
    'activate' => 'تفعيل',
    'delete' => 'حذف',

    'invite' => [
        'action' => 'دعوة مشرف',
        'phone' => 'رقم جوال المشرف',
        'template' => 'قالب جاهز',
        'ready' => 'جهّزنا رسالة واتساب. أرسلها للمشرف.',
        'message' => "دعوة للانضمام كمشرف في مبادرة العجاونة:\n:url",
    ],

    'join' => [
        'phone_fixed' => 'هذا رقمك المسجّل في الدعوة ولا يمكن تغييره.',
        'submit' => 'إنشاء الحساب',
    ],

    'errors' => [
        'phone' => 'أدخل رقم جوال سعودي صحيح.',
        'template' => 'القالب غير معروف.',
        'token_used' => 'رابط الدعوة غير صالح أو استُخدم من قبل.',
        'token_expired' => 'انتهت صلاحية رابط الدعوة.',
        'delete_blocked' => 'لا يمكن حذف هذا المشرف لارتباطه بسجلات أخرى.',
    ],

    'permission_labels' => [
        'stats_view' => 'عرض المؤشرات',
        'transfers_view' => 'عرض الحوالات',
        'transfers_review' => 'مراجعة الحوالات',
        'transfers_assign' => 'إسناد المراجعة',
        'users_view' => 'عرض المستخدمين',
        'users_suspend' => 'تعطيل المبادرين',
        'recovery_handle' => 'معالجة الاستعادة',
        'recovery_other_number' => 'رابط لرقم مختلف',
        'recovery_change_phone' => 'تعديل رقم الدخول',
        'security_view' => 'عرض الأمان',
        'stats_recovery' => 'إحصائيات الاستعادة',
        'settings_manage' => 'إدارة الإعدادات',
        'beneficiaries_manage' => 'إدارة المستفيدين',
        'content_manage' => 'إدارة المحتوى',
        'messages_view' => 'عرض الرسائل',
        'converter_use' => 'حاسبة التاريخ',
        'supervisors_manage' => 'إدارة المشرفين',
    ],

];
