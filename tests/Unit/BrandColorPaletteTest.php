<?php

declare(strict_types=1);

use App\Support\BrandColorPalette;

/*
|--------------------------------------------------------------------------
| لوحة ألوان الهوية المضبوطة (T16 — docs/SPEC.md FR-49)
|--------------------------------------------------------------------------
| كل مفتاح افتراضي في config/branding.php يجب أن يمرّ فحص التباين فعليًا،
| ومفتاح ضعيف التباين مُصنَّع للاختبار يُرفض.
*/

test('المفاتيح الافتراضية للألوان الأساسية والثانوية تحقق تباين AA', function (string $role, string $key): void {
    expect(BrandColorPalette::isAccessible($role, $key))->toBeTrue();
})->with([
    'الأساسي الافتراضي (كحلي)' => [BrandColorPalette::ROLE_PRIMARY, 'navy'],
    'الأساسي البديل (أخضر)' => [BrandColorPalette::ROLE_PRIMARY, 'forest'],
    'الثانوي الافتراضي (ذهبي)' => [BrandColorPalette::ROLE_SECONDARY, 'gold'],
    'الثانوي البديل (نحاسي)' => [BrandColorPalette::ROLE_SECONDARY, 'brass'],
]);

test('مفتاح غير معرَّف في اللوحة يُرفض', function (): void {
    expect(BrandColorPalette::isAccessible(BrandColorPalette::ROLE_PRIMARY, 'not-a-real-key'))->toBeFalse();
});

test('مفتاح ضعيف التباين يُرفض ولو كان معرَّفًا في اللوحة', function (): void {
    config(['branding.palette.primary.weak' => [
        'label' => 'ضعيف (للاختبار فقط)',
        'light' => '#F5F5F0',
        'dark' => '#101010',
    ]]);

    expect(BrandColorPalette::isAccessible(BrandColorPalette::ROLE_PRIMARY, 'weak'))->toBeFalse();
});

test('اللون الثانوي يُفحص بحد النص الكبير 3، والأساسي بحد النص العادي 4.5', function (): void {
    // ذهبي الهوية (gold) يحقق 3:1 دون 4.5:1 على الخلفية الفاتحة، فيُقبل ثانويًا (نص كبير عريض
    // فقط في dashboard.blade.php وhome.blade.php) ويُرفض لو استُخدم أساسيًا (نص عادي وخلفية زر).
    config(['branding.palette.primary.gold' => config('branding.palette.secondary.gold')]);

    expect(BrandColorPalette::isAccessible(BrandColorPalette::ROLE_SECONDARY, 'gold'))->toBeTrue()
        ->and(BrandColorPalette::isAccessible(BrandColorPalette::ROLE_PRIMARY, 'gold'))->toBeFalse();
});
