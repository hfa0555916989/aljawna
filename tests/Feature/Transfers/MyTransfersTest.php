<?php

declare(strict_types=1);

use App\Livewire\Transfers\Index;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| صفحة "حوالاتي": كل حوالات المبادر (T07 — FR-16)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->initiator = User::factory()->create();
});

test('تعرض كل حوالات المبادر بالمستفيد كاملًا والمبلغ والتاريخ ورابط الإيصال', function (): void {
    $longName = str_repeat('عبدالرحمن ', 6).'العجاوني';
    $open = Beneficiary::factory()->approved()->create(['display_name' => $longName]);
    $closed = Beneficiary::factory()->approved()->closed()->create(['display_name' => 'مستفيد مغلق وهمي للاختبار']);

    $first = Transfer::factory()->for($this->initiator)->for($open)->create(['amount' => '1250.50', 'transferred_on' => '2026-09-20']);
    $second = Transfer::factory()->for($this->initiator)->for($closed)->repeated()->create(['amount' => '300.00']);

    $component = Livewire::actingAs($this->initiator)->test(Index::class)
        ->assertSee($longName)
        ->assertSee('مستفيد مغلق وهمي للاختبار')
        ->assertSee('1,250.50 ر.س')
        ->assertSee('300 ر.س')
        ->assertSee('20 سبتمبر 2026م')
        ->assertSee(trans_choice('transfers.index.count', 2, ['count' => 2]))
        ->assertDontSeeHtml('truncate');

    foreach ([$first, $second] as $transfer) {
        $component->assertSeeHtml('/receipts/'.$transfer->id.'?expires=');
    }

    expect(substr_count($component->html(), 'signature='))->toBe(2);
});

test('لا تعرض حوالات مبادر آخر', function (): void {
    $other = Transfer::factory()->create(['amount' => '7777.00']);

    Livewire::actingAs($this->initiator)->test(Index::class)
        ->assertDontSee('7,777 ر.س')
        ->assertDontSeeHtml('/receipts/'.$other->id.'?')
        ->assertSee(__('transfers.index.empty'));
});

test('روابط الإيصالات لا تُحفظ في حالة المكوّن', function (): void {
    Transfer::factory()->for($this->initiator)->create();

    $component = Livewire::actingAs($this->initiator)->test(Index::class);

    expect(json_encode($component->snapshot))->not->toContain('signature')->not->toContain('receipt_path');
});

test('المبادر يفتح الصفحة، ورسالة النجاح تظهر بعد الرفع', function (): void {
    $this->actingAs($this->initiator)
        ->withSession(['status' => __('transfers.index.created')])
        ->get(route('transfers.index'))
        ->assertOk()
        ->assertSee(__('transfers.index.created'))
        ->assertSee(route('transfers.create'), false);
});
