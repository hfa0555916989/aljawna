<?php

declare(strict_types=1);

use App\Livewire\Beneficiaries\Index;
use App\Models\Beneficiary;
use App\Support\HijriDate;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| قائمة المبادرات بتبويبين (T06 — docs/SPEC.md FR-28, FR-35, FR-36)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->available = Beneficiary::factory()->approved()->create([
        'display_name' => 'سالم ماجد تركي المتاح',
        'account_holder' => 'صاحب حساب المتاح الوهمي',
    ]);
    $this->closed = Beneficiary::factory()->approved()->closed()->create([
        'display_name' => 'نايف عبدالرحمن تركي المغلق',
        'account_holder' => 'صاحب حساب المغلق الوهمي',
    ]);
    $this->pending = Beneficiary::factory()->create([
        'display_name' => 'ماجد سالم نايف غير المعتمد',
        'account_holder' => 'صاحب حساب غير المعتمد الوهمي',
    ]);
    $this->pendingClosed = Beneficiary::factory()->closed()->create([
        'display_name' => 'تركي نايف سالم المغلق غير المعتمد',
        'account_holder' => 'صاحب حساب المغلق غير المعتمد الوهمي',
    ]);
    $this->revoked = Beneficiary::factory()->approvalRevoked()->create([
        'display_name' => 'عبدالرحمن ماجد سالم الملغى اعتماده',
        'account_holder' => 'صاحب حساب الملغى اعتماده الوهمي',
    ]);
});

test('تبويب المتاحة الافتراضي يعرض المعتمدة المتاحة فقط', function (): void {
    $this->get(route('beneficiaries.index'))
        ->assertOk()
        ->assertSeeText($this->available->display_name)
        ->assertDontSeeText($this->closed->display_name)
        ->assertDontSeeText($this->pending->display_name)
        ->assertDontSeeText($this->pendingClosed->display_name)
        ->assertDontSeeText($this->revoked->display_name)
        ->assertSee('href="'.route('beneficiaries.show', $this->available).'"', false);
});

test('تبويب المغلقة يعرض كل المغلقة معتمدة أو لا، ولا يعرض المتاحة', function (): void {
    $this->get(route('beneficiaries.index', ['tab' => 'closed']))
        ->assertOk()
        ->assertSeeText($this->closed->display_name)
        ->assertSeeText($this->pendingClosed->display_name)
        ->assertDontSeeText($this->available->display_name)
        ->assertDontSeeText($this->pending->display_name)
        ->assertDontSeeText($this->revoked->display_name);
});

test('بطاقة المغلقة غير المعتمدة رابط عادي لصفحتها كبقية البطاقات', function (): void {
    $html = (string) $this->get(route('beneficiaries.index', ['tab' => 'closed']))->assertOk()->getContent();

    foreach ([$this->closed, $this->pendingClosed] as $beneficiary) {
        expect(substr_count($html, 'href="'.route('beneficiaries.show', $beneficiary).'"'))->toBe(2);
    }

    $this->get(route('beneficiaries.show', $this->pendingClosed))
        ->assertOk()
        ->assertSeeText($this->pendingClosed->display_name)
        ->assertDontSee('copyField(', false);
});

test('التبديل بين التبويبين عبر Livewire يغيّر القائمة', function (): void {
    Livewire::test(Index::class)
        ->assertSeeText($this->available->display_name)
        ->set('tab', 'closed')
        ->assertSeeText($this->closed->display_name)
        ->assertSeeText($this->pendingClosed->display_name)
        ->assertDontSeeText($this->available->display_name)
        ->set('tab', 'available')
        ->assertSeeText($this->available->display_name)
        ->assertDontSeeText($this->closed->display_name);
});

test('قيمة تبويب غير معروفة تعود إلى المتاحة', function (): void {
    Livewire::withQueryParams(['tab' => 'pending'])
        ->test(Index::class)
        ->assertSeeText($this->available->display_name)
        ->assertDontSeeText($this->pending->display_name)
        ->assertDontSeeText($this->closed->display_name);
});

test('عدد تبويب المتاحة للمعتمدة فقط، وعدد المغلقة لكل مغلقة', function (): void {
    Beneficiary::factory()->approved()->create();

    expect(Livewire::test(Index::class)->instance()->counts())->toBe(['available' => 2, 'closed' => 2]);
});

test('القائمة لا تحتوي أي بيانات بنكية في HTML ولا في استجابات Livewire', function (string $tab): void {
    $component = Livewire::withQueryParams(['tab' => $tab])->test(Index::class)->call('$refresh');

    foreach ([$this->available, $this->closed, $this->pending, $this->pendingClosed, $this->revoked] as $beneficiary) {
        assertNoBankData($component, $beneficiary);
    }
})->with(['available', 'closed']);

test('صفحة القائمة الكاملة لا تحتوي بيانات بنكية ولا أسماء غير المعتمدين', function (): void {
    $html = $this->get(route('beneficiaries.index'))->assertOk()->getContent();

    foreach ([$this->available, $this->closed, $this->pending, $this->pendingClosed, $this->revoked] as $beneficiary) {
        foreach (bankSecretsOf($beneficiary) as $secret) {
            expect($html)->not->toContain($secret);
        }
    }

    expect($html)->not->toContain($this->pending->display_name)
        ->not->toContain($this->revoked->display_name);
});

test('اسم المستفيد الطويل (60 حرفًا) يُعرض كاملًا بلا اقتطاع', function (): void {
    $longName = 'عبدالرحمن بن عبدالعزيز بن عبدالمحسن بن عبدالكريم بن العجاوني';
    expect(mb_strlen($longName))->toBe(60);

    Beneficiary::factory()->approved()->create(['display_name' => $longName]);

    $response = $this->get(route('beneficiaries.index'))->assertOk()->assertSeeText($longName);

    expect($response->getContent())
        ->not->toContain('truncate')
        ->not->toContain('line-clamp')
        ->not->toContain('text-ellipsis');
});

test('البطاقة تعرض نسبة التقدّم والمواعيد بالتقويمين', function (): void {
    $this->travelTo(now()->startOfDay());

    $this->get(route('beneficiaries.index'))
        ->assertOk()
        ->assertSee('<progress class="progress-bar mt-1.5" max="100" value="0"', false)
        ->assertSeeText(__('site.beneficiaries.progress'))
        ->assertSeeText(HijriDate::format($this->available->target_deadline))
        ->assertSeeText(HijriDate::gregorian($this->available->target_deadline))
        ->assertSeeText(HijriDate::format($this->available->recommended_deadline))
        ->assertSeeText(HijriDate::countdown($this->available->target_deadline));
});

test('صفحة القائمة فيها العنوان والوصف ووسوم المشاركة', function (): void {
    $title = __('site.beneficiaries.title').' — '.config('app.name');

    $this->get(route('beneficiaries.index'))
        ->assertOk()
        ->assertSee('<title>'.e($title).'</title>', false)
        ->assertSee('<meta name="description" content="'.e(__('site.beneficiaries.description')).'">', false)
        ->assertSee('<meta property="og:title" content="'.e($title).'">', false);
});
