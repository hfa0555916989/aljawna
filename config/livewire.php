<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Livewire: الرفع المؤقت على القرص local صراحة (docs/RUNBOOK.md القسم 6)
|--------------------------------------------------------------------------
|
| الافتراضي في Livewire هو قرص FILESYSTEM_DISK، وهو على Laravel Cloud قرص s3
| (aljawna-receipts هو الـ Default disk عمدًا، FILESYSTEM_DISK=receipts). لو تبعه Livewire
| لانتقل إلى الرفع المباشر من المتصفح إلى الـ bucket برابط موقّع، فتمنعه سياسة أمان المحتوى
| (connect-src 'self') ويتعطّل رفع الإيصالات والصور. لذلك يُرفع الملف مؤقتًا إلى الخادم نفسه،
| مستقلًا عن FILESYSTEM_DISK، ثم يحفظه التطبيق في قرصه.
|
| تنبيه: يصحّ هذا ما دامت هناك نسخة واحدة من التطبيق. عند تفعيل autoscaling أو أكثر من
| نسخة يجب مراجعته، لأن الملف المؤقت قد يكون على نسخة غير التي تستقبل طلب الحفظ.
|
| Livewire يدمج هذا الملف مع إعداده على المستوى الأعلى فقط، فيُكتب المفتاح
| temporary_file_upload كاملًا بقيمه الافتراضية ما عدا disk.
|
*/

return [

    'temporary_file_upload' => [
        'disk' => 'local',
        'rules' => null,
        'directory' => null,
        'middleware' => null,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5,
        'cleanup' => true,
    ],

];
