<?php

declare(strict_types=1);

use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use App\Support\Money;

/*
|--------------------------------------------------------------------------
| أداة "نظرة عامة" في لوحة الإدارة (T08 — docs/SPEC.md §7, FR-31، stats.view)
|--------------------------------------------------------------------------
*/

test('أداة نظرة عامة تعرض مؤشرات مجموع المبادرة الصحيحة', function (): void {
    Beneficiary::factory()->approved()->count(2)->create();
    Beneficiary::factory()->approved()->closed()->create();
    $beneficiary = Beneficiary::factory()->approved()->create();
    Transfer::factory()->for($beneficiary)->create(['amount' => '750.00']);

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSeeTextInOrder([
            __('dashboard.cards.available'), '3',
            __('dashboard.cards.closed'), '1',
            __('dashboard.cards.initiators'), '1',
            __('dashboard.cards.total'), Money::format('750.00'),
            __('dashboard.cards.receipts'), '1',
            __('dashboard.cards.average'), Money::format('750.00'),
            __('dashboard.cards.remaining'),
        ]);
});
