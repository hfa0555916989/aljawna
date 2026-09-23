<?php

declare(strict_types=1);

use App\Filament\Resources\Beneficiaries\Pages\EditBeneficiary;
use App\Models\Beneficiary;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| الاعتماد والإغلاق وإعادة الفتح (T05 — FR-29, FR-32)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

test('الاعتماد بعد المراجعة يُظهر المبادرة للعامة ويسجّل من اعتمدها ومتى', function (): void {
    $beneficiary = Beneficiary::factory()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionVisible('approve')
        ->callAction('approve')
        ->assertNotified('اعتُمدت المبادرة.');

    $beneficiary->refresh();

    expect($beneficiary->isApproved())->toBeTrue()
        ->and($beneficiary->approved_by)->toBe($this->admin->id)
        ->and(Beneficiary::public()->whereKey($beneficiary->id)->exists())->toBeTrue();
});

test('زر الاعتماد يختفي بعد الاعتماد', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionHidden('approve');
});

test('الاعتماد يتطلب نافذة تأكيد', function (): void {
    $beneficiary = Beneficiary::factory()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->mountAction('approve')
        ->assertMountedActionModalSee('بعد الاعتماد تظهر المبادرة للعامة');

    expect($beneficiary->refresh()->isApproved())->toBeFalse();
});

test('إغلاق المبادرة يوقف استقبال الحوالات ويبقيها في القائمة العامة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionHidden('reopen')
        ->callAction('close')
        ->assertNotified('أُغلقت المبادرة.');

    $beneficiary->refresh();

    expect($beneficiary->status->value)->toBe('closed')
        ->and($beneficiary->acceptsTransfers())->toBeFalse()
        ->and(Beneficiary::available()->whereKey($beneficiary->id)->exists())->toBeFalse()
        ->and(Beneficiary::public()->whereKey($beneficiary->id)->exists())->toBeTrue();
});

test('إعادة فتح المبادرة المغلقة تعيدها متاحة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->closed()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionHidden('close')
        ->callAction('reopen')
        ->assertNotified('أُعيد فتح المبادرة.');

    expect($beneficiary->refresh()->acceptsTransfers())->toBeTrue();
});

test('لا يوجد حذف للمستفيدين', function (): void {
    $beneficiary = Beneficiary::factory()->create();

    Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()])
        ->assertActionDoesNotExist('delete');
});
