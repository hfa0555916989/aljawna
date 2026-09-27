<?php

declare(strict_types=1);

return [

    'navigation' => 'الأمان',

    'tabs' => [
        'suspicious' => 'الحسابات المشبوهة',
        'attempts' => 'سجل المحاولات',
        'audit' => 'سجل التدقيق',
    ],

    'suspicious' => [
        'empty' => 'لا حسابات مشبوهة حاليًا.',
        'rules' => [
            'failed_logins' => ':count محاولة دخول فاشلة خلال :hours ساعة',
            'registrations_per_ip' => ':count حسابات مسجّلة من IP واحد خلال :hours ساعة',
            'recovery_requests' => ':count طلبات استعادة خلال :hours ساعة',
        ],
    ],

    'suspended' => [
        'heading' => 'الحسابات المعطّلة',
        'empty' => 'لا حسابات معطّلة.',
    ],

    'status' => [
        'active' => 'فعّال',
        'suspended' => 'معطّل',
    ],

    'suspend' => 'تعطيل الحساب',
    'activate' => 'إعادة التفعيل',
    'cancel' => 'إلغاء',
    'reason' => 'السبب',
    'suspended_done' => 'عُطِّل الحساب وانتهت جلساته.',
    'activated_done' => 'أُعيد تفعيل الحساب.',

    'attempts' => [
        'empty' => 'لا محاولات دخول بعد.',
        'phone' => 'الرقم',
        'ip' => 'IP',
        'succeeded' => 'ناجحة',
        'failed' => 'فاشلة',
    ],

    'audit' => [
        'empty' => 'لا سجلات تطابق المرشحات.',
        'action' => 'الإجراء',
        'actor' => 'المنفّذ',
        'subject' => 'على',
        'from' => 'من تاريخ',
        'to' => 'إلى تاريخ',
        'all' => 'الكل',
        'system' => 'النظام',
    ],

    'audit_actions' => [
        'admin' => [
            'demoted' => 'تخفيض مدير',
        ],
        'branding' => [
            'updated' => 'تعديل الهوية',
        ],
        'beneficiary' => [
            'approved' => 'اعتماد مستفيد',
            'bank_account_updated' => 'تعديل حساب بنكي لمستفيد',
            'closed' => 'إغلاق مستفيد',
            'created' => 'تسجيل مستفيد',
            'reopened' => 'إعادة فتح مستفيد',
        ],
        'permissions' => [
            'updated' => 'تعديل صلاحيات',
        ],
        'recovery' => [
            'cancelled' => 'إلغاء طلب استعادة',
            'claimed' => 'استلام طلب استعادة',
            'completed' => 'إتمام استعادة',
            'expired' => 'انتهاء طلب استعادة',
            'link_sent' => 'إصدار رابط استعادة',
            'phone_changed' => 'تعديل رقم دخول',
            'requested' => 'طلب استعادة',
        ],
        'supervisor' => [
            'activated' => 'تفعيل مشرف',
            'contact_updated' => 'تعديل ظهور رقم مشرف',
            'deactivated' => 'تعطيل مشرف',
            'deleted' => 'حذف مشرف',
            'invited' => 'دعوة مشرف',
            'joined' => 'انضمام مشرف',
        ],
        'transfer' => [
            'assigned' => 'إسناد مراجعة حوالة',
            'commented' => 'تعليق على حوالة',
            'final_assigned' => 'إسناد مراجعة نهائية',
            'final_reviewed' => 'مراجعة نهائية لحوالة',
            'matched' => 'مطابقة حوالة',
        ],
        'user' => [
            'activated' => 'إعادة تفعيل حساب مبادر',
            'suspended' => 'تعطيل حساب مبادر',
        ],
    ],

    'errors' => [
        'not_initiator' => 'التعطيل من هنا لحسابات المبادرين فقط.',
        'reason_required' => 'اكتب السبب أولًا.',
        'reason_too_long' => 'السبب طويل. الحد :max حرفًا.',
        'reason_has_iban' => 'لا يُسمح بكتابة رقم آيبان في السبب، لأنه يُحفظ في سجل التدقيق.',
        'reason_has_phone' => 'لا يُسمح بكتابة رقم جوال في السبب، لأنه يُحفظ في سجل التدقيق.',
    ],

];
