<?php

declare(strict_types=1);

use App\BeneficiaryStatus;
use App\Livewire\Home;
use App\Models\Beneficiary;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| الصفحة الرئيسية: بطاقتا العدّ المتجاورتان (T06 — docs/SPEC.md §1, §7, FR-34)
|--------------------------------------------------------------------------
*/

test('العدّادان يحسبان المعتمدة فقط: المتاحة والمغلقة', function (): void {
    Beneficiary::factory()->approved()->count(3)->create();
    Beneficiary::factory()->approved()->closed()->count(2)->create();
    Beneficiary::factory()->create();
    Beneficiary::factory()->closed()->create();
    Beneficiary::factory()->approvalRevoked()->create();
    Beneficiary::factory()->approvalRevoked()->closed()->create();

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('dir="ltr" data-counter="available">3</span>', false)
        ->assertSee('dir="ltr" data-counter="closed">2</span>', false);
});

test('العدّادان صفر عند عدم وجود مبادرات معتمدة', function (): void {
    Beneficiary::factory()->create();

    Livewire::test(Home::class)
        ->assertSeeHtml('data-counter="available">0</span>')
        ->assertSeeHtml('data-counter="closed">0</span>');
});

test('العدّادان متجاوران في شبكة من عمودين على كل العروض', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeInOrder(['grid max-w-xl grid-cols-2', 'data-counter="available"', 'data-counter="closed"'], false);
});

test('العدّادان يتحدّثان فور إغلاق مبادرة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create();

    $component = Livewire::test(Home::class)
        ->assertSeeHtml('data-counter="available">1</span>')
        ->assertSeeHtml('data-counter="closed">0</span>');

    $beneficiary->forceFill(['status' => BeneficiaryStatus::Closed])->save();

    $component->call('$refresh')
        ->assertSeeHtml('data-counter="available">0</span>')
        ->assertSeeHtml('data-counter="closed">1</span>');
});

test('الصفحة الرئيسية لا تعرض أي بيانات بنكية', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['account_holder' => 'صاحب حساب وهمي للاختبار']);

    assertNoBankData(Livewire::test(Home::class), $beneficiary);
});

test('الصفحة الرئيسية فيها العنوان والوصف ووسوم المشاركة', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>'.config('app.name').'</title>', false)
        ->assertSee('<meta name="description" content="'.e(__('site.meta.description')).'">', false)
        ->assertSee('<meta property="og:title" content="'.e(config('app.name')).'">', false)
        ->assertSee('<meta property="og:description" content="'.e(__('site.meta.description')).'">', false)
        ->assertSee('<meta property="og:url" content="'.route('home').'">', false)
        ->assertSee('<link rel="canonical" href="'.route('home').'">', false);
});

test('الصفحة الرئيسية فيها التعريف وخطوات المشاركة وقسم أحدث المبادرات', function (): void {
    $this->get(route('home'))
        ->assertOk()
        ->assertSeeText(__('site.home.about_heading'))
        ->assertSeeText(__('site.home.how_heading'))
        ->assertSeeText(__('site.home.latest_heading'))
        ->assertSeeText(__('site.home.latest_empty'));
});
