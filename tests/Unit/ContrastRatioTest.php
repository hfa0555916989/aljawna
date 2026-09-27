<?php

declare(strict_types=1);

use App\Support\ContrastRatio;

/*
|--------------------------------------------------------------------------
| نسبة تباين WCAG 2.1 (T16 — docs/SPEC.md FR-49)
|--------------------------------------------------------------------------
| القيم مرجعية معروفة: تباين أسود/أبيض هو الأقصى (21:1)، وتباين لونين
| متقاربَين ضعيف جدًا (أقل من 1.5:1).
*/

test('نسبة التباين بين الأسود والأبيض 21 (الحد الأقصى)', function (): void {
    expect(round(ContrastRatio::of('#000000', '#FFFFFF'), 2))->toBe(21.0);
});

test('نسبة تباين اللون مع نفسه هي 1 (بلا تباين)', function (): void {
    expect(round(ContrastRatio::of('#1F2A44', '#1F2A44'), 2))->toBe(1.0);
});

test('لون ضعيف التباين يُرفض عند الحد 4.5', function (): void {
    // ذهبي فاتح على خلفية بيضاء قريبة منه في السطوع: تباين ضعيف.
    expect(ContrastRatio::passesAA('#C99A2E', '#FFFFFF', ContrastRatio::AA_NORMAL))->toBeFalse();
});

test('لون سليم التباين يُقبل عند الحد 4.5', function (): void {
    // كحلي الشعار على خلفية بيضاء: تباين قوي جدًا.
    expect(ContrastRatio::passesAA('#1F2A44', '#FFFFFF', ContrastRatio::AA_NORMAL))->toBeTrue();
});

test('حد النص الكبير 3 أكثر تسامحًا من حد النص العادي 4.5', function (): void {
    // نحاسي على خلفية بيضاء: يفشل حد النص العادي وينجح حد النص الكبير.
    expect(ContrastRatio::passesAA('#B58A2A', '#FFFFFF', ContrastRatio::AA_NORMAL))->toBeFalse()
        ->and(ContrastRatio::passesAA('#B58A2A', '#FFFFFF', ContrastRatio::AA_LARGE))->toBeTrue();
});

test('الترتيب لا يغيّر النتيجة', function (): void {
    expect(ContrastRatio::of('#1F2A44', '#FFFFFF'))->toBe(ContrastRatio::of('#FFFFFF', '#1F2A44'));
});
