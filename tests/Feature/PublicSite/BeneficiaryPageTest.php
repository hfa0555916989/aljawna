<?php

declare(strict_types=1);

use App\BeneficiaryStatus;
use App\Livewire\Beneficiaries\Show;
use App\Models\Beneficiary;
use App\Support\HijriDate;
use App\Support\Money;
use App\Support\SaudiIban;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| صفحة المستفيد: الحساب مع النسخ للمتاحة فقط (T06 — docs/SPEC.md FR-25, FR-26, FR-28, FR-37)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->available = Beneficiary::factory()->approved()->create([
        'display_name' => 'سالم ماجد تركي المتاح',
        'account_holder' => 'صاحب حساب المتاح الوهمي',
        'bank_name' => 'مصرف الإنماء',
        'account_number' => '123456789012345',
        'iban' => SaudiIban::fromParts('05', '000000123456789012'),
    ]);
    $this->closed = Beneficiary::factory()->approved()->closed()->create([
        'display_name' => 'نايف عبدالرحمن تركي المغلق',
        'account_holder' => 'صاحب حساب المغلق الوهمي',
        'bank_name' => 'البنك السعودي الأول',
        'account_number' => '987654321098765',
        'iban' => SaudiIban::fromParts('45', '000000987654321098'),
    ]);
});

test('الزائر يرى حساب المبادرة المتاحة كاملًا مع زري النسخ', function (): void {
    $iban = $this->available->iban;

    $response = $this->get(route('beneficiaries.show', $this->available))
        ->assertOk()
        ->assertSeeText($this->available->display_name)
        ->assertSeeText('مصرف الإنماء')
        ->assertSeeText('صاحب حساب المتاح الوهمي')
        ->assertSee('dir="ltr" class="min-w-0 select-all break-all text-start font-mono text-base font-semibold text-ink sm:text-lg">123456789012345</span>', false)
        ->assertSee('>'.SaudiIban::grouped($iban).'</span>', false)
        ->assertSee('x-data="copyField(', false)
        ->assertSeeText(__('site.copy.copied'))
        ->assertSeeText(__('site.copy.manual'))
        ->assertSee('value="'.$iban.'"', false)
        ->assertSee('value="123456789012345"', false);

    expect(substr_count((string) $response->getContent(), 'x-on:click="copy()"'))->toBe(2);
});

test('قيمة النسخ هي الآيبان المضغوط بلا مسافات', function (): void {
    $iban = $this->available->iban;

    $this->get(route('beneficiaries.show', $this->available))
        ->assertOk()
        ->assertSee("copyField('{$iban}')", false)
        ->assertDontSee("copyField('".SaudiIban::grouped($iban)."')", false);
});

test('المبادرة المغلقة تُعرض بلا أي بيانات بنكية في HTML', function (): void {
    $response = $this->get(route('beneficiaries.show', $this->closed))
        ->assertOk()
        ->assertSeeText($this->closed->display_name)
        ->assertSeeText(__('site.show.closed_notice'))
        ->assertDontSee('copyField(', false)
        ->assertDontSeeText('البنك السعودي الأول');

    foreach (bankSecretsOf($this->closed) as $secret) {
        expect($response->getContent())->not->toContain($secret);
    }
});

test('المبادرة المغلقة لا تكشف بياناتها البنكية في استجابات Livewire', function (): void {
    $component = Livewire::test(Show::class, ['beneficiary' => $this->closed])->call('$refresh');

    assertNoBankData($component, $this->closed);
    expect($component->snapshot['data'])->toBe(['beneficiaryId' => $this->closed->id]);
});

test('لقطة Livewire للمبادرة المتاحة لا تحمل الحساب البنكي', function (): void {
    $component = Livewire::test(Show::class, ['beneficiary' => $this->available]);

    expect($component->snapshot['data'])->toBe(['beneficiaryId' => $this->available->id]);
});

test('إغلاق المبادرة يُخفي حسابها من الاستجابة التالية', function (): void {
    $component = Livewire::test(Show::class, ['beneficiary' => $this->available])
        ->assertSeeText('صاحب حساب المتاح الوهمي');

    $this->available->forceFill(['status' => BeneficiaryStatus::Closed])->save();

    $component->call('$refresh');

    assertNoBankData($component, $this->available);
});

test('صفحة غير المعتمد تُفتح (200) بمعلوماته العامة وتخفي بيانات الحساب', function (Closure $makeBeneficiary): void {
    /** @var Beneficiary $beneficiary */
    $beneficiary = $makeBeneficiary();
    $this->travelTo(now()->startOfDay());

    $response = $this->get(route('beneficiaries.show', $beneficiary))
        ->assertOk()
        ->assertSeeText($beneficiary->display_name)
        ->assertSeeText(__('site.beneficiaries.target', ['amount' => Money::format($beneficiary->target_amount)]))
        ->assertSeeText(HijriDate::format($beneficiary->target_deadline))
        ->assertSeeText(HijriDate::format($beneficiary->wedding_date))
        ->assertDontSee('copyField(', false)
        ->assertDontSeeText('مصرف الراجحي')
        ->assertDontSeeText(__('site.show.account_heading'));

    foreach (bankSecretsOf($beneficiary) as $secret) {
        expect($response->getContent())->not->toContain($secret);
    }

    assertNoBankData(Livewire::test(Show::class, ['beneficiary' => $beneficiary])->call('$refresh'), $beneficiary);
})->with([
    'بانتظار الاعتماد' => fn (): Beneficiary => Beneficiary::factory()->create(['account_holder' => 'صاحب حساب وهمي بانتظار الاعتماد']),
    'مغلق غير معتمد' => fn (): Beneficiary => Beneficiary::factory()->closed()->create(['account_holder' => 'صاحب حساب وهمي مغلق غير معتمد']),
    'أُلغي اعتماده بعد تعديل بنكي' => fn (): Beneficiary => Beneficiary::factory()->approvalRevoked()->create(['account_holder' => 'صاحب حساب وهمي ملغى اعتماده']),
]);

test('غير المعتمد غير المغلق لا يُوصف بأنه متاح ولا مغلق', function (): void {
    $pending = Beneficiary::factory()->create();

    $this->get(route('beneficiaries.show', $pending))
        ->assertOk()
        ->assertSeeText(__('site.show.account_unavailable'))
        ->assertDontSeeText(__('site.show.closed_notice'))
        ->assertDontSee('inline-flex shrink-0 items-center rounded-full border', false);
});

test('مستفيد غير موجود أو معرّف غير رقمي يعطي 404', function (): void {
    $this->get('/beneficiaries/999999')->assertNotFound();
    $this->get('/beneficiaries/abc')->assertNotFound();
});

test('إلغاء الاعتماد بعد فتح الصفحة يُخفي الحساب من الاستجابة التالية', function (): void {
    $component = Livewire::test(Show::class, ['beneficiary' => $this->available])
        ->assertSeeText('صاحب حساب المتاح الوهمي');

    $this->available->forceFill(['approved_at' => null, 'approval_revoked_at' => now()])->save();

    $component->call('$refresh')
        ->assertOk()
        ->assertSeeText($this->available->display_name);

    assertNoBankData($component, $this->available);
});

test('لا يمكن تبديل المستفيد من المتصفح إلى آخر', function (): void {
    $pending = Beneficiary::factory()->create();

    Livewire::test(Show::class, ['beneficiary' => $this->available])
        ->set('beneficiaryId', $pending->id);
})->throws(CannotUpdateLockedPropertyException::class);

test('الصفحة تعرض المواعيد بالتقويمين مع العدّ التنازلي للمتاحة', function (): void {
    $this->travelTo(now()->startOfDay());

    $this->get(route('beneficiaries.show', $this->available))
        ->assertOk()
        ->assertSeeText(HijriDate::format($this->available->target_deadline))
        ->assertSeeText(HijriDate::format($this->available->recommended_deadline))
        ->assertSeeText(HijriDate::format($this->available->wedding_date))
        ->assertSeeText(HijriDate::gregorian($this->available->wedding_date))
        ->assertSeeText((string) HijriDate::weekday($this->available->wedding_date))
        ->assertSeeText(HijriDate::countdown($this->available->target_deadline));
});

test('صفحة المستفيد فيها العنوان والوصف ووسوم المشاركة دون أي بيانات بنكية', function (): void {
    $title = $this->available->display_name.' — '.config('app.name');

    $response = $this->get(route('beneficiaries.show', $this->available))
        ->assertOk()
        ->assertSee('<title>'.e($title).'</title>', false)
        ->assertSee('<meta property="og:title" content="'.e($title).'">', false)
        ->assertSee('<meta property="og:url" content="'.route('beneficiaries.show', $this->available).'">', false);

    preg_match('/<meta name="description" content="([^"]*)">/u', (string) $response->getContent(), $matches);

    expect($matches[1] ?? '')->toContain(e($this->available->display_name))
        ->not->toContain($this->available->iban)
        ->not->toContain($this->available->account_number);
});
