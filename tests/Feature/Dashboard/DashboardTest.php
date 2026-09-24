<?php

declare(strict_types=1);

use App\Livewire\Dashboard;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| لوحة المبادر /dashboard (T08 — docs/SPEC.md §7, FR-31)
|--------------------------------------------------------------------------
*/

test('اللوحة تعرض بطاقات المؤشرات لمجموع المبادرة', function (): void {
    Beneficiary::factory()->approved()->count(2)->create();
    Beneficiary::factory()->approved()->closed()->create();
    $beneficiary = Beneficiary::factory()->approved()->create();
    Transfer::factory()->for($beneficiary)->create(['amount' => '500.00']);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeHtml('data-stat="available">3</span>')
        ->assertSeeHtml('data-stat="closed">1</span>')
        ->assertSeeHtml('data-stat="initiators">1</span>')
        ->assertSeeHtml('data-stat="receipts">1</span>');
});

test('اللوحة تعرض مواعيد أول مستفيد متاح افتراضيًا، وتتبدّل عند اختيار غيره', function (): void {
    $first = Beneficiary::factory()->approved()->create(['target_deadline' => today()->addMonth()]);
    $second = Beneficiary::factory()->approved()->create(['target_deadline' => today()->addMonths(3)]);

    Livewire::actingAs(User::factory()->create())
        ->test(Dashboard::class)
        ->assertSet('selectedBeneficiaryId', $first->id)
        ->set('selectedBeneficiaryId', $second->id)
        ->assertSet('selectedBeneficiaryId', $second->id);
});

test('اللوحة تعرض رسالة فراغ للمواعيد إن لم توجد مبادرات متاحة', function (): void {
    Livewire::actingAs(User::factory()->create())
        ->test(Dashboard::class)
        ->assertSeeText(__('dashboard.dates.empty'));
});

test('أحدث المبادرات في اللوحة تعرض الاسم الأول فقط دون اسم المبادر كاملًا', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['display_name' => 'مستفيد الاختبار']);
    $initiator = User::factory()->create(['full_name' => 'سالم ماجد تركي العجاوني']);
    Transfer::factory()->for($beneficiary)->for($initiator)->create(['amount' => '300.00']);

    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText('سالم')
        ->assertDontSeeText('سالم ماجد تركي العجاوني');
});

test('اللوحة تعرض رسالة فراغ لأحدث المبادرات إن لم توجد حوالات', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSeeText(__('dashboard.latest.empty'));
});

test('اللوحة تعرض روابط رفع الحوالة وحوالاتي', function (): void {
    $this->actingAs(User::factory()->create())
        ->get('/dashboard')
        ->assertOk()
        ->assertSee('href="'.route('transfers.create').'"', false)
        ->assertSee('href="'.route('transfers.index').'"', false);
});
