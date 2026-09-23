<?php

declare(strict_types=1);

use App\Filament\Resources\Beneficiaries\Pages\CreateBeneficiary;
use App\Models\Beneficiary;
use App\Models\User;
use App\Support\SaudiIban;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| تسجيل مستفيد (T05 — FR-29, §12.10)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('نموذج التسجيل يعرض الحقول الخمسة بأسمائها ثم المبلغ والمواعيد', function (): void {
    $this->get('/admin/beneficiaries/create')
        ->assertOk()
        ->assertSeeInOrder([
            'اسم المستفيد المنشور',
            'اسم صاحب الحساب كما هو في المصرف',
            'اسم المصرف',
            'رقم الحساب',
            'رقم الآيبان',
            'المبلغ المستهدف',
            'الموعد المستهدف',
            'الموعد المستحسن',
            'موعد الزواج',
        ]);
});

test('التسجيل يحفظ المستفيد متاحًا وغير معتمد باسم من سجّله', function (): void {
    Livewire::test(CreateBeneficiary::class)
        ->fillForm(beneficiaryFormData())
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $beneficiary = Beneficiary::query()->sole();

    expect($beneficiary->display_name)->toBe('سالم ماجد تركي العجاوني')
        ->and($beneficiary->status->value)->toBe('active')
        ->and($beneficiary->target_amount)->toBe('45000.00')
        ->and($beneficiary->wedding_date->toDateString())->toBe('2027-01-15')
        ->and($beneficiary->created_by)->toBe($this->admin->id)
        ->and($beneficiary->isApproved())->toBeFalse()
        ->and(Beneficiary::public()->exists())->toBeFalse();
});

test('الآيبان ورقم الحساب يُطبَّعان عند الحفظ', function (): void {
    $iban = SaudiIban::fromParts('80', '000000123456789012');

    Livewire::test(CreateBeneficiary::class)
        ->fillForm(beneficiaryFormData([
            'iban' => strtolower(implode(' ', str_split($iban, 4))),
            'account_number' => '١٢٣ ٤٥٦ ٧٨٩',
        ]))
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Beneficiary::query()->sole())
        ->iban->toBe($iban)
        ->account_number->toBe('123456789');
});

test('آيبان غير صحيح يُرفض برسالة عربية واضحة', function (string $iban, string $message): void {
    Livewire::test(CreateBeneficiary::class)
        ->fillForm(beneficiaryFormData(['iban' => $iban]))
        ->call('create')
        ->assertHasFormErrors(['iban'])
        ->assertSee($message);

    expect(Beneficiary::query()->exists())->toBeFalse();
})->with([
    'طول ناقص' => ['SA038000000060801016751', 'رقم الآيبان يجب أن يبدأ بـ SA ويليه 22 رقمًا (24 خانة).'],
    'بادئة غير سعودية' => ['AE070331234567890123456', 'رقم الآيبان يجب أن يبدأ بـ SA ويليه 22 رقمًا (24 خانة).'],
    'خانة تحقق خاطئة' => ['SA0480000000608010167519', 'رقم الآيبان غير صحيح: خانة التحقق لا تطابق.'],
]);

test('رقم الحساب بغير الأرقام يُرفض', function (string $accountNumber): void {
    Livewire::test(CreateBeneficiary::class)
        ->fillForm(beneficiaryFormData(['account_number' => $accountNumber]))
        ->call('create')
        ->assertHasFormErrors(['account_number'])
        ->assertSee('رقم الحساب يجب أن يتكون من أرقام فقط.');
})->with([
    'حروف' => ['12345ABC'],
    'شرطة' => ['1234-5678'],
]);

test('الحقول كلها مطلوبة', function (): void {
    Livewire::test(CreateBeneficiary::class)
        ->fillForm(array_fill_keys(array_keys(beneficiaryFormData()), null))
        ->call('create')
        ->assertHasFormErrors([
            'display_name' => 'required',
            'account_holder' => 'required',
            'bank_name' => 'required',
            'account_number' => 'required',
            'iban' => 'required',
            'target_amount' => 'required',
            'target_deadline' => 'required',
            'recommended_deadline' => 'required',
            'wedding_date' => 'required',
        ]);
});

test('لا يُقبل آيبان مكتوب داخل الاسم المنشور أو اسم صاحب الحساب', function (string $field): void {
    Livewire::test(CreateBeneficiary::class)
        ->fillForm(beneficiaryFormData([$field => 'سالم العجاوني SA03 8000 0000 6080 1016 7519']))
        ->call('create')
        ->assertHasFormErrors([$field]);
})->with(['display_name', 'account_holder']);

test('عدم تطابق رمز المصرف في الآيبان مع اسم المصرف ينبّه ولا يمنع', function (): void {
    Livewire::test(CreateBeneficiary::class)
        ->fillForm(beneficiaryFormData([
            'bank_name' => 'بنك البلاد',
            'iban' => SaudiIban::fromParts('80', '000000123456789012'),
        ]))
        ->assertSee('رمز المصرف في الآيبان يخص «مصرف الراجحي» ولا يطابق اسم المصرف المُدخل')
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Beneficiary::query()->sole()->bank_name)->toBe('بنك البلاد');
});

test('لا تنبيه حين يطابق اسم المصرف رمزه بأي صيغة مقبولة', function (string $bankName): void {
    Livewire::test(CreateBeneficiary::class)
        ->fillForm(beneficiaryFormData(['bank_name' => $bankName]))
        ->assertDontSee('ولا يطابق اسم المصرف المُدخل');
})->with(['مصرف الراجحي', 'الراجحي', 'Al Rajhi Bank']);

test('كل تاريخ تظهر تحته معاينته بالهجري (أم القرى)', function (): void {
    Livewire::test(CreateBeneficiary::class)
        ->fillForm(beneficiaryFormData(['wedding_date' => '2026-06-16']))
        ->assertSee('بالهجري: ١ محرم ١٤٤٨ هـ');
});
