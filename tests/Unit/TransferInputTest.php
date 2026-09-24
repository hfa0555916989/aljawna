<?php

declare(strict_types=1);

use App\Support\AmountInput;
use App\Support\BankReference;
use App\Support\ReceiptType;

/*
|--------------------------------------------------------------------------
| تطبيع مدخلات الحوالة وكشف نوع الإيصال (T07)
|--------------------------------------------------------------------------
*/

test('تطبيع المبلغ', function (?string $input, ?string $expected): void {
    expect(AmountInput::normalize($input))->toBe($expected);
})->with([
    ['100', '100.00'],
    ['100.5', '100.50'],
    [' 1 500 ', '1500.00'],
    ['١٠٠٫٢٥', '100.25'],
    ['12,345.67', '12345.67'],
    ['0', null],
    ['0.00', null],
    ['-5', null],
    ['1.234', null],
    ['12345678901', null],
    ['.5', null],
    ['5.', null],
    ['', null],
    [null, null],
]);

test('تطبيع رقم العملية', function (?string $input, ?string $expected, bool $valid): void {
    expect(BankReference::normalize($input))->toBe($expected)
        ->and(BankReference::isValid($input))->toBe($valid);
})->with([
    ['  ', null, true],
    [null, null, true],
    ['ab-12 ٣', 'AB-123', true],
    ['-AB', '-AB', false],
    ['AB#1', 'AB#1', false],
    [str_repeat('A', 65), str_repeat('A', 65), false],
]);

test('كشف نوع الإيصال من المحتوى لا من الاسم', function (string $contents, ?ReceiptType $expected): void {
    expect(ReceiptType::detect($contents))->toBe($expected);
})->with([
    'JPEG' => fn (): array => [jpegBytes(), ReceiptType::Jpeg],
    'PNG' => fn (): array => [pngBytes(), ReceiptType::Png],
    'PDF' => fn (): array => [pdfBytes(), ReceiptType::Pdf],
    'نص' => fn (): array => ['hello', null],
    'PHP' => fn (): array => ['<?php echo 1;', null],
    'GIF' => fn (): array => [(string) base64_decode('R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw=='), null],
]);

test('الامتدادات المقبولة وحدها تُعرَّف', function (): void {
    expect(ReceiptType::fromExtension('JPG'))->toBe(ReceiptType::Jpeg)
        ->and(ReceiptType::fromExtension('jpeg'))->toBe(ReceiptType::Jpeg)
        ->and(ReceiptType::fromExtension('png'))->toBe(ReceiptType::Png)
        ->and(ReceiptType::fromExtension('pdf'))->toBe(ReceiptType::Pdf)
        ->and(ReceiptType::fromExtension('gif'))->toBeNull()
        ->and(ReceiptType::fromExtension('php'))->toBeNull()
        ->and(ReceiptType::fromExtension(''))->toBeNull();
});
