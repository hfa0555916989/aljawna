<?php

declare(strict_types=1);

use App\Support\SaudiPhone;

/*
|--------------------------------------------------------------------------
| اختبار تطبيع الجوال السعودي (T01)
|--------------------------------------------------------------------------
| يغطي الصيغ الثلاث المذكورة في tasks/T01-users-base.md ورفض غير السعودي.
*/

test('يطبّع صيغة 05XXXXXXXX المحلية', function (): void {
    expect(SaudiPhone::normalize('0512345678'))->toBe('+966512345678');
});

test('يطبّع صيغة +9665XXXXXXXX الدولية', function (): void {
    expect(SaudiPhone::normalize('+966512345678'))->toBe('+966512345678');
});

test('يطبّع صيغة 009665XXXXXXXX', function (): void {
    expect(SaudiPhone::normalize('00966512345678'))->toBe('+966512345678');
});

test('يزيل المسافات والرموز الفاصلة بين الأرقام', function (): void {
    expect(SaudiPhone::normalize('+966 51 234 5678'))->toBe('+966512345678');
    expect(SaudiPhone::normalize('05-1234-5678'))->toBe('+966512345678');
    expect(SaudiPhone::normalize('(05) 1234 5678'))->toBe('+966512345678');
});

test('يقبل الأرقام العربية (الهندية) ويحوّلها', function (): void {
    expect(SaudiPhone::normalize('٠٥١٢٣٤٥٦٧٨'))->toBe('+966512345678');
    expect(SaudiPhone::normalize('+٩٦٦٥١٢٣٤٥٦٧٨'))->toBe('+966512345678');
});

test('يقبل كل أرقام البدايات الخلوية السعودية من 50 إلى 59', function (int $prefix): void {
    $phone = '0'.$prefix.'1234567';

    expect(SaudiPhone::normalize($phone))->toBe('+966'.$prefix.'1234567');
})->with(range(50, 59));

test('يرفض رقمًا أقصر من الصيغة الصحيحة', function (): void {
    expect(SaudiPhone::normalize('05123456'))->toBeNull();
});

test('يرفض رقمًا أطول من الصيغة الصحيحة', function (): void {
    expect(SaudiPhone::normalize('051234567890'))->toBeNull();
});

test('يرفض رقمًا غير سعودي برمز دولة مختلف', function (): void {
    expect(SaudiPhone::normalize('+20101234567'))->toBeNull();
    expect(SaudiPhone::normalize('+971501234567'))->toBeNull();
});

test('يرفض رقمًا محليًا لا يبدأ بـ 5 بعد الصفر', function (): void {
    expect(SaudiPhone::normalize('0112345678'))->toBeNull();
});

test('يرفض نصًا يحتوي أحرفًا بدلًا من الأرقام', function (): void {
    expect(SaudiPhone::normalize('05ABCDEFGH'))->toBeNull();
});

test('يرفض القيمة الفارغة أو null', function (): void {
    expect(SaudiPhone::normalize(''))->toBeNull();
    expect(SaudiPhone::normalize(null))->toBeNull();
});

test('isValid تعكس نتيجة normalize', function (): void {
    expect(SaudiPhone::isValid('0512345678'))->toBeTrue();
    expect(SaudiPhone::isValid('0012345678'))->toBeFalse();
});
