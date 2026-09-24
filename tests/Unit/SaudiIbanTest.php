<?php

declare(strict_types=1);

use App\Support\SaudiIban;

/*
|--------------------------------------------------------------------------
| الآيبان السعودي: الصيغة وخانة التحقق ورمز المصرف (T05 — docs/SPEC.md §12.10)
|--------------------------------------------------------------------------
| SA0380000000608010167519 هو المثال المنشور في مواصفة ISO 13616 لسجل
| الآيبان، وليس حسابًا لشخص.
*/

const DOCUMENTED_SAUDI_IBAN = 'SA0380000000608010167519';

test('الآيبان الصحيح يُقبل بصيغته المضغوطة', function (): void {
    expect(SaudiIban::isValid(DOCUMENTED_SAUDI_IBAN))->toBeTrue()
        ->and(SaudiIban::bankCode(DOCUMENTED_SAUDI_IBAN))->toBe('80');
});

test('الآيبان يُقبل بمسافات وحروف صغيرة وأرقام عربية ويُطبَّع', function (string $input): void {
    expect(SaudiIban::isValid($input))->toBeTrue()
        ->and(SaudiIban::normalize($input))->toBe(DOCUMENTED_SAUDI_IBAN);
})->with([
    'مجموعات من أربع خانات' => 'SA03 8000 0000 6080 1016 7519',
    'حروف صغيرة' => 'sa0380000000608010167519',
    'أرقام عربية هندية' => 'SA٠٣٨٠٠٠٠٠٠٠٦٠٨٠١٠١٦٧٥١٩',
]);

test('الآيبان الخاطئ الصيغة يُرفض', function (string $input): void {
    expect(SaudiIban::hasValidFormat($input))->toBeFalse()
        ->and(SaudiIban::isValid($input))->toBeFalse()
        ->and(SaudiIban::bankCode($input))->toBeNull();
})->with([
    'أقصر بخانة (23)' => 'SA038000000060801016751',
    'أطول بخانة (25)' => 'SA03800000006080101675190',
    'بادئة دولة أخرى' => 'AE070331234567890123456',
    'بلا بادئة' => '030380000000608010167519',
    'حرف داخل الأرقام' => 'SA038000000060801016751X',
    'فارغ' => '',
]);

test('الآيبان بخانة تحقق لا تطابق يُرفض رغم صحة الصيغة', function (string $input): void {
    expect(SaudiIban::hasValidFormat($input))->toBeTrue()
        ->and(SaudiIban::isValid($input))->toBeFalse();
})->with([
    'رقم تحقق مختلف' => 'SA0480000000608010167519',
    'تغيير رقم في الحساب' => 'SA0380000000608010167518',
    'تبديل رقمين متجاورين' => 'SA0380000000608010167591',
]);

test('بناء آيبان من رمز المصرف ورقم الحساب يعطي خانة تحقق صحيحة', function (): void {
    expect(SaudiIban::fromParts('80', '000000608010167519'))->toBe(DOCUMENTED_SAUDI_IBAN)
        ->and(SaudiIban::isValid(SaudiIban::fromParts('05', '123456789012345678')))->toBeTrue();
});

test('عرض الآيبان في مجموعات من أربع خانات دون تغيير قيمته المضغوطة', function (): void {
    expect(SaudiIban::grouped(DOCUMENTED_SAUDI_IBAN))->toBe('SA03 8000 0000 6080 1016 7519')
        ->and(SaudiIban::normalize(SaudiIban::grouped(DOCUMENTED_SAUDI_IBAN)))->toBe(DOCUMENTED_SAUDI_IBAN);
});
