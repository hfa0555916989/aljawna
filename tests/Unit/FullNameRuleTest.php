<?php

declare(strict_types=1);

use App\Rules\FullName;
use Illuminate\Support\Facades\Validator;

/*
|--------------------------------------------------------------------------
| اختبار قاعدة الاسم الكامل (T01)
|--------------------------------------------------------------------------
| docs/SPEC.md §3: أربع كلمات فأكثر، أحرف عربية أو لاتينية ومسافات فقط،
| وكل كلمة حرفان على الأقل.
*/

function validateFullName(mixed $value): bool
{
    return Validator::make(['name' => $value], ['name' => ['required', new FullName]])->passes();
}

test('يرفض اسمًا من ثلاث كلمات فقط', function (): void {
    expect(validateFullName('أحمد محمد علي'))->toBeFalse();
});

test('يقبل اسمًا من أربع كلمات', function (): void {
    expect(validateFullName('أحمد محمد علي العجاوني'))->toBeTrue();
});

test('يقبل اسمًا من خمس كلمات فأكثر', function (): void {
    expect(validateFullName('أحمد محمد علي سعيد العجاوني'))->toBeTrue();
});

test('يرفض اسمًا يحتوي أرقامًا', function (): void {
    expect(validateFullName('أحمد محمد علي 2024'))->toBeFalse();
});

test('يرفض اسمًا يحتوي رموزًا', function (): void {
    expect(validateFullName('أحمد-محمد علي العجاوني'))->toBeFalse();
});

test('يرفض كلمة من حرف واحد فقط', function (): void {
    expect(validateFullName('أ محمد علي العجاوني'))->toBeFalse();
});

test('يقبل اسمًا لاتينيًا من أربع كلمات', function (): void {
    expect(validateFullName('Ahmad Mohammed Ali Alajawni'))->toBeTrue();
});

test('يرفض القيمة الفارغة', function (): void {
    expect(validateFullName(''))->toBeFalse();
    expect(validateFullName('   '))->toBeFalse();
});

test('يتسامح مع مسافات زائدة بين الكلمات وفي البداية والنهاية', function (): void {
    expect(validateFullName('  أحمد   محمد   علي   العجاوني  '))->toBeTrue();
});

test('يرفض قيمة غير نصية', function (): void {
    expect(validateFullName(12345678))->toBeFalse();
});
