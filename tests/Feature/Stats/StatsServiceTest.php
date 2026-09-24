<?php

declare(strict_types=1);

use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use App\Services\StatsService;

/*
|--------------------------------------------------------------------------
| مؤشرات الحوالات لمستفيد واحد ولمجموع المبادرة (T08 — docs/SPEC.md §7, FR-31)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->stats = app(StatsService::class);
});

test('صفر حوالات: كل المؤشرات صفرية والمتبقي 100%', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);

    expect($this->stats->forBeneficiary($beneficiary))->toBe([
        'initiators_count' => 0,
        'receipts_count' => 0,
        'total' => '0.00',
        'average' => '0.00',
        'target' => '1000.00',
        'progress_percentage' => 0,
        'remaining_percentage' => 100,
    ]);
});

test('حوالة واحدة: الأرقام تطابق تلك الحوالة تمامًا', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);
    Transfer::factory()->for($beneficiary)->create(['amount' => '250.00']);

    expect($this->stats->forBeneficiary($beneficiary))->toBe([
        'initiators_count' => 1,
        'receipts_count' => 1,
        'total' => '250.00',
        'average' => '250.00',
        'target' => '1000.00',
        'progress_percentage' => 25,
        'remaining_percentage' => 75,
    ]);
});

test('تجاوز الهدف: النسبة 100% والمتبقي 0% والإجمالي الحقيقي ظاهر', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);
    Transfer::factory()->for($beneficiary)->create(['amount' => '1000.00']);
    Transfer::factory()->for($beneficiary)->create(['amount' => '500.00']);

    $result = $this->stats->forBeneficiary($beneficiary);

    expect($result['progress_percentage'])->toBe(100)
        ->and($result['remaining_percentage'])->toBe(0)
        ->and($result['total'])->toBe('1500.00');
});

test('عدة حوالات لمبادر واحد تُحسب مرة واحدة في عدد من حوّلوا', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);
    $initiator = User::factory()->create();

    Transfer::factory()->for($beneficiary)->for($initiator)->count(3)->create(['amount' => '100.00']);

    $result = $this->stats->forBeneficiary($beneficiary);

    expect($result['initiators_count'])->toBe(1)
        ->and($result['receipts_count'])->toBe(3)
        ->and($result['total'])->toBe('300.00');
});

test('مجموع المبادرة: مبادر واحد لمستفيدين يُحسب مرة واحدة، والمستهدف والنسبة لمجموع الكل', function (): void {
    $initiator = User::factory()->create();
    $beneficiaryA = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);
    $beneficiaryB = Beneficiary::factory()->approved()->create(['target_amount' => '2000.00']);

    Transfer::factory()->for($beneficiaryA)->for($initiator)->create(['amount' => '100.00']);
    Transfer::factory()->for($beneficiaryB)->for($initiator)->create(['amount' => '200.00']);
    Transfer::factory()->for($beneficiaryB)->create(['amount' => '300.00']);

    $result = $this->stats->forBeneficiary();

    expect($result['initiators_count'])->toBe(2)
        ->and($result['receipts_count'])->toBe(3)
        ->and($result['total'])->toBe('600.00')
        ->and($result['target'])->toBe('3000.00')
        ->and($result['progress_percentage'])->toBe(20)
        ->and($result['remaining_percentage'])->toBe(80);
});

test('الكاش يُبطَل فورًا عند إضافة حوالة جديدة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);

    expect($this->stats->forBeneficiary($beneficiary)['total'])->toBe('0.00')
        ->and($this->stats->forBeneficiary()['total'])->toBe('0.00');

    Transfer::factory()->for($beneficiary)->create(['amount' => '400.00']);

    expect($this->stats->forBeneficiary($beneficiary)['total'])->toBe('400.00')
        ->and($this->stats->forBeneficiary()['total'])->toBe('400.00');
});

test('الكاش يُبطَل فورًا عند تعديل حوالة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);
    $transfer = Transfer::factory()->for($beneficiary)->create(['amount' => '400.00']);

    expect($this->stats->forBeneficiary($beneficiary)['total'])->toBe('400.00');

    $transfer->update(['amount' => '900.00']);

    expect($this->stats->forBeneficiary($beneficiary)['total'])->toBe('900.00');
});

test('أحدث الحوالات: الاسم الأول للمبادر فقط، وحتى 6 حوالات بالأحدث أولًا', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create();
    $initiator = User::factory()->create(['full_name' => 'سالم ماجد تركي العجاوني']);

    $transfers = Transfer::factory()->for($beneficiary)->for($initiator)->count(8)->create();

    $recent = $this->stats->recentTransfers();
    $expectedIds = $transfers->sortByDesc('id')->take(StatsService::RECENT_LIMIT)->pluck('id')->values()->all();

    expect($recent)->toHaveCount(StatsService::RECENT_LIMIT)
        ->and($recent->pluck('id')->all())->toBe($expectedIds)
        ->and($recent->first()->user->firstName())->toBe('سالم');
});
