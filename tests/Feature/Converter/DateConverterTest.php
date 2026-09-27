<?php

declare(strict_types=1);

use App\Filament\Pages\Converter;
use App\Models\User;
use App\Support\HijriDate;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| حاسبة التاريخ الهجري والميلادي (T15 — docs/SPEC.md §8, FR-27)
|--------------------------------------------------------------------------
| التحويل ثنائي الاتجاه وفق أم القرى عبر App\Support\HijriDate (T06)، ورسالة
| واضحة عند يوم هجري غير موجود في شهره (مثل 30 في شهر من 29 يومًا).
*/

const CONVERTER_PAGE = 'converter';

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');
});

function converterOfficer(): User
{
    return User::factory()->supervisor()->withPermissions(['converter.use'])->create();
}

test('من لا يملك converter.use يُرفض بـ 403', function (): void {
    $supervisor = User::factory()->supervisor()->create();

    $this->actingAs($supervisor)->get(adminPath(CONVERTER_PAGE))->assertForbidden();
});

test('المشرف الممنوح converter.use يفتح الصفحة', function (): void {
    $this->actingAs(converterOfficer())->get(adminPath(CONVERTER_PAGE))->assertOk()->assertSee(__('converter.navigation'));
});

test('المدير يفتح الصفحة ضمنيًا دون منح مباشر', function (): void {
    $this->actingAs(User::factory()->admin()->create())->get(adminPath(CONVERTER_PAGE))->assertOk();

    expect(Converter::canAccess())->toBeTrue();
});

test('المبادر لا يصل إلى الصفحة (404)', function (): void {
    $this->actingAs(User::factory()->create())->get(adminPath(CONVERTER_PAGE))->assertNotFound();
});

test('الزائر يُحوَّل إلى صفحة الدخول الموحّدة', function (): void {
    $this->get(adminPath(CONVERTER_PAGE))->assertRedirect(route('login'));
});

test('سحب converter.use يمنع الصفحة فورًا', function (): void {
    $supervisor = converterOfficer();

    $this->actingAs($supervisor)->get(adminPath(CONVERTER_PAGE))->assertOk();

    $supervisor->revokePermissionTo('converter.use');

    $this->actingAs($supervisor->fresh())->get(adminPath(CONVERTER_PAGE))->assertForbidden();
});

test('يحوّل تواريخ ميلادية معروفة إلى الهجري الصحيح (أم القرى)', function (string $gregorian, string $hijri, string $weekday): void {
    $result = Livewire::actingAs(converterOfficer())
        ->test(Converter::class)
        ->set('gregorianDate', $gregorian)
        ->instance()
        ->result;

    expect($result)->toBe(['hijri' => $hijri, 'gregorian' => HijriDate::gregorian($gregorian), 'weekday' => $weekday]);
})->with([
    'أول محرم 1448' => ['2026-06-16', '1 محرم 1448 هـ', 'الثلاثاء'],
    'أول رمضان 1446' => ['2025-03-01', '1 رمضان 1446 هـ', 'السبت'],
    'آخر رمضان 1446 (29 يومًا)' => ['2025-03-29', '29 رمضان 1446 هـ', 'السبت'],
    'أول شوال 1446 بعد شهر من 29 يومًا' => ['2025-03-30', '1 شوال 1446 هـ', 'الأحد'],
    'يوم النحر 1445' => ['2024-06-16', '10 ذو الحجة 1445 هـ', 'الأحد'],
]);

test('يحوّل تواريخ هجرية معروفة إلى الميلادي الصحيح (أم القرى)', function (int $year, int $month, int $day, string $gregorian): void {
    $result = Livewire::actingAs(converterOfficer())
        ->test(Converter::class)
        ->call('selectDirection', Converter::DIRECTION_HIJRI_TO_GREGORIAN)
        ->set('hijriYear', (string) $year)
        ->set('hijriMonth', (string) $month)
        ->set('hijriDay', (string) $day)
        ->instance()
        ->result;

    expect($result['gregorian'])->toBe(HijriDate::gregorian($gregorian));
})->with([
    'أول محرم 1448' => [1448, 1, 1, '2026-06-16'],
    'أول رمضان 1446' => [1446, 9, 1, '2025-03-01'],
    'آخر رمضان 1446 (29 يومًا)' => [1446, 9, 29, '2025-03-29'],
    'أول شوال 1446 بعد شهر من 29 يومًا' => [1446, 10, 1, '2025-03-30'],
    'يوم النحر 1445' => [1445, 12, 10, '2024-06-16'],
]);

test('يوم 30 في شهر رمضان 1446 (29 يومًا) يعرض رسالة واضحة لا خطأ فنيًا', function (): void {
    $page = Livewire::actingAs(converterOfficer())
        ->test(Converter::class)
        ->call('selectDirection', Converter::DIRECTION_HIJRI_TO_GREGORIAN)
        ->set('hijriYear', '1446')
        ->set('hijriMonth', '9')
        ->set('hijriDay', '30');

    expect($page->instance()->result)->toBeNull()
        ->and($page->instance()->hasCompleteInput)->toBeTrue();

    $page->assertSeeHtml('data-converter-error')
        ->assertSee(__('converter.errors.invalid_hijri_day'))
        ->assertDontSeeHtml('data-converter-result');
});

test('اختيار اتجاه غير معروف يُتجاهل ويبقى الاتجاه الحالي', function (): void {
    Livewire::actingAs(converterOfficer())
        ->test(Converter::class)
        ->call('selectDirection', 'not-a-direction')
        ->assertSet('direction', Converter::DIRECTION_GREGORIAN_TO_HIJRI);
});

test('لا نتيجة تُعرض قبل إكمال حقول الاتجاه الهجري', function (): void {
    $page = Livewire::actingAs(converterOfficer())
        ->test(Converter::class)
        ->call('selectDirection', Converter::DIRECTION_HIJRI_TO_GREGORIAN)
        ->set('hijriYear', '1446')
        ->set('hijriMonth', '9');

    expect($page->instance()->hasCompleteInput)->toBeFalse()
        ->and($page->instance()->result)->toBeNull();

    $page->assertDontSeeHtml('data-converter-result')->assertDontSeeHtml('data-converter-error');
});

test('الصفحة تعرض أسماء الأشهر الهجرية الاثني عشر بالعربية', function (): void {
    Livewire::actingAs(converterOfficer())
        ->test(Converter::class)
        ->call('selectDirection', Converter::DIRECTION_HIJRI_TO_GREGORIAN)
        ->assertSee(['محرم', 'صفر', 'ربيع الأول', 'ربيع الآخر', 'جمادى الأولى', 'جمادى الآخرة', 'رجب', 'شعبان', 'رمضان', 'شوال', 'ذو القعدة', 'ذو الحجة']);
});

test('تنبيه اختلاف الرؤية الشرعية يظهر مع نتيجة صحيحة', function (): void {
    Livewire::actingAs(converterOfficer())
        ->test(Converter::class)
        ->set('gregorianDate', '2026-06-16')
        ->assertSee(__('converter.disclaimer'));
});
