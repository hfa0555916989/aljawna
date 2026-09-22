<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| اختبار إعدادات المشروع الأساسية (T00)
|--------------------------------------------------------------------------
| يتحقق من أن الإعدادات المطلوبة في tasks/T00-setup.md مضبوطة فعليًا:
| اللغة العربية، والتوقيت Asia/Riyadh، وبنية ملفات الترجمة lang/ar.
| اختبار وحدة صرف (لا يلمس قاعدة البيانات أو HTTP).
*/

test('اللغة الافتراضية والاحتياطية للتطبيق عربية', function (): void {
    expect(config('app.locale'))->toBe('ar');
    expect(config('app.fallback_locale'))->toBe('ar');
});

test('توقيت العرض الافتراضي هو آسيا/الرياض', function (): void {
    expect(config('app.timezone'))->toBe('Asia/Riyadh');
});

test('بنية ملفات الترجمة lang/ar موجودة وكاملة', function (): void {
    $expectedFiles = ['auth.php', 'pagination.php', 'passwords.php', 'validation.php'];

    foreach ($expectedFiles as $file) {
        $path = lang_path('ar/'.$file);

        expect(is_file($path))
            ->toBeTrue("الملف [lang/ar/{$file}] غير موجود.");
    }
});
