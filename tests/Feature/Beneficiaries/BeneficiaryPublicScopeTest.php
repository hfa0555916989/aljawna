<?php

declare(strict_types=1);

use App\Models\Beneficiary;

/*
|--------------------------------------------------------------------------
| النطاقات العامة: لا يظهر غير المعتمد للعامة (T05 — FR-29, FR-32, FR-35)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->pending = Beneficiary::factory()->create();
    $this->pendingClosed = Beneficiary::factory()->closed()->create();
    $this->approved = Beneficiary::factory()->approved()->create();
    $this->approvedClosed = Beneficiary::factory()->approved()->closed()->create();
});

test('النطاق العام public() يُخفي غير المعتمد ويُبقي المعتمد المتاح والمغلق', function (): void {
    expect(Beneficiary::public()->pluck('id')->sort()->values()->all())
        ->toBe(collect([$this->approved->id, $this->approvedClosed->id])->sort()->values()->all());
});

test('النطاق available() يُخفي غير المعتمد والمغلق', function (): void {
    expect(Beneficiary::available()->pluck('id')->all())->toBe([$this->approved->id]);
});

test('النطاق closed() يعرض المعتمد المغلق فقط', function (): void {
    expect(Beneficiary::closed()->pluck('id')->all())->toBe([$this->approvedClosed->id]);
});

test('استقبال الحوالات للمعتمد المتاح فقط', function (): void {
    expect($this->approved->acceptsTransfers())->toBeTrue()
        ->and($this->approvedClosed->acceptsTransfers())->toBeFalse()
        ->and($this->pending->acceptsTransfers())->toBeFalse()
        ->and($this->pendingClosed->acceptsTransfers())->toBeFalse();
});
