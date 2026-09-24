<?php

declare(strict_types=1);

use App\Livewire\Home;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| قسم "أحدث المبادرات" في الصفحة الرئيسية (T08 — docs/SPEC.md §7, §12.6, FR-31)
|--------------------------------------------------------------------------
*/

test('قسم أحدث المبادرات يعرض الاسم الأول للمبادر فقط، ولا يعرض جواله', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['display_name' => 'مستفيد الاختبار']);
    $initiator = User::factory()->create(['full_name' => 'سالم ماجد تركي العجاوني']);
    Transfer::factory()->for($beneficiary)->for($initiator)->create(['amount' => '300.00']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSeeText('سالم')
        ->assertDontSeeText('سالم ماجد تركي العجاوني')
        ->assertDontSee($initiator->phone);
});

test('قسم أحدث المبادرات لا يعرض أي بيانات بنكية للمستفيد', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['account_holder' => 'صاحب حساب وهمي للاختبار']);
    Transfer::factory()->for($beneficiary)->create();

    $this->get(route('home'));

    assertNoBankData(Livewire::test(Home::class), $beneficiary);
});
