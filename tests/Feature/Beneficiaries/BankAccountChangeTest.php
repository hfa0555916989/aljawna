<?php

declare(strict_types=1);

use App\Actions\Beneficiaries\UpdateBeneficiary;
use App\Exceptions\BankAccountChangeNotConfirmed;
use App\Filament\Resources\Beneficiaries\Pages\EditBeneficiary;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\User;
use App\Support\SaudiIban;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| حماية الحساب البنكي: تأكيد صريح وتسجيل القديم والجديد (T05 — FR-33, §12.10)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');

    $this->supervisor = User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create();
    $this->beneficiary = Beneficiary::factory()->approved()->create([
        'bank_name' => 'مصرف الراجحي',
        'iban' => SaudiIban::fromParts('80', '000000111111111111'),
        'account_number' => '111111111111111',
    ]);
    $this->actingAs($this->supervisor);
});

function editBeneficiaryPage(Beneficiary $beneficiary): Testable
{
    return Livewire::test(EditBeneficiary::class, ['record' => $beneficiary->getKey()]);
}

dataset('bank field changes', [
    'الآيبان' => ['iban', SaudiIban::fromParts('80', '000000222222222222')],
    'رقم الحساب' => ['account_number', '222222222222222'],
    'اسم صاحب الحساب' => ['account_holder', 'نايف سالم تركي المطيري'],
    'اسم المصرف' => ['bank_name', 'الراجحي'],
]);

test('تعديل حقل غير بنكي يُحفظ مباشرة دون تأكيد ولا يُسجَّل تغيير حساب', function (): void {
    editBeneficiaryPage($this->beneficiary)
        ->fillForm(['target_amount' => '70000'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionNotMounted('confirmBankChange');

    expect($this->beneficiary->refresh()->target_amount)->toBe('70000.00')
        ->and(AuditLog::query()->where('action', UpdateBeneficiary::AUDIT_ACTION)->exists())->toBeFalse();
});

test('تعديل حقل بنكي لا يُحفظ دون تأكيد ويفتح نافذة التأكيد', function (string $field, string $newValue): void {
    $oldValue = (string) $this->beneficiary->getAttribute($field);

    editBeneficiaryPage($this->beneficiary)
        ->fillForm([$field => $newValue])
        ->call('save')
        ->assertActionMounted('confirmBankChange')
        ->assertMountedActionModalSee([$oldValue, $newValue]);

    expect($this->beneficiary->refresh()->getAttribute($field))->toBe($oldValue)
        ->and(AuditLog::query()->exists())->toBeFalse();
})->with('bank field changes');

test('تأكيد تعديل الحقل البنكي يحفظه ويسجّل من غيّر والقيمة القديمة والجديدة', function (string $field, string $newValue): void {
    $oldValue = (string) $this->beneficiary->getAttribute($field);

    editBeneficiaryPage($this->beneficiary)
        ->fillForm([$field => $newValue])
        ->call('save')
        ->callMountedAction()
        ->assertHasNoFormErrors();

    $audit = AuditLog::query()->sole();

    expect($this->beneficiary->refresh()->getAttribute($field))->toBe($newValue)
        ->and($audit->action)->toBe(UpdateBeneficiary::AUDIT_ACTION)
        ->and($audit->actor_id)->toBe($this->supervisor->id)
        ->and($audit->subject_type)->toBe($this->beneficiary->getMorphClass())
        ->and($audit->subject_id)->toBe($this->beneficiary->id)
        ->and($audit->meta)->toBe([
            'changes' => [$field => ['old' => $oldValue, 'new' => $newValue]],
            'approval_revoked' => true,
        ]);
})->with('bank field changes');

test('تعديل أي حقل بنكي يلغي الاعتماد فيختفي المستفيد من القوائم العامة', function (string $field, string $newValue): void {
    expect(Beneficiary::public()->whereKey($this->beneficiary->id)->exists())->toBeTrue();

    editBeneficiaryPage($this->beneficiary)
        ->fillForm([$field => $newValue])
        ->call('save')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertActionVisible('approve');

    $this->beneficiary->refresh();

    expect($this->beneficiary->isApproved())->toBeFalse()
        ->and($this->beneficiary->approved_by)->toBeNull()
        ->and(Beneficiary::public()->whereKey($this->beneficiary->id)->exists())->toBeFalse()
        ->and(Beneficiary::available()->whereKey($this->beneficiary->id)->exists())->toBeFalse();
})->with('bank field changes');

test('تعديل حقل غير بنكي يبقي الاعتماد', function (): void {
    editBeneficiaryPage($this->beneficiary)
        ->fillForm(['display_name' => 'سالم ماجد تركي الجديد', 'target_amount' => '70000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->beneficiary->refresh()->isApproved())->toBeTrue();
});

test('تعديل مستفيد مضت مواعيده لا يُرفض بسبب الماضي، فالمنع عند التسجيل فقط', function (): void {
    $this->beneficiary->forceFill([
        'target_deadline' => today()->subMonths(3),
        'recommended_deadline' => today()->subMonths(2),
        'wedding_date' => today()->subMonth(),
    ])->save();

    editBeneficiaryPage($this->beneficiary)
        ->fillForm(['target_amount' => '70000'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->beneficiary->refresh()->target_amount)->toBe('70000.00');
});

test('لا يجوز تعديل الآيبان إلى آيبان مسجّل لمستفيد آخر', function (): void {
    $other = Beneficiary::factory()->create();
    $oldIban = $this->beneficiary->iban;

    editBeneficiaryPage($this->beneficiary)
        ->fillForm(['iban' => $other->iban])
        ->call('save')
        ->assertHasFormErrors(['iban'])
        ->assertActionNotMounted('confirmBankChange');

    expect($this->beneficiary->refresh()->iban)->toBe($oldIban);
});

test('تسلسل المواعيد يُفرض عند التعديل أيضًا', function (): void {
    editBeneficiaryPage($this->beneficiary)
        ->fillForm(['wedding_date' => $this->beneficiary->recommended_deadline->copy()->subDay()->toDateString()])
        ->call('save')
        ->assertHasFormErrors(['wedding_date']);
});

test('تعديل حساب مستفيد غير معتمد يسجّل أنه لم يُلغَ اعتماد', function (): void {
    $pending = Beneficiary::factory()->create();

    app(UpdateBeneficiary::class)->handle(
        $this->supervisor,
        $pending,
        ['account_number' => '999999999999999'],
        bankChangeConfirmed: true,
    );

    expect(AuditLog::query()->sole()->meta['approval_revoked'])->toBeFalse();
});

test('إلغاء نافذة التأكيد لا يحفظ الحساب', function (): void {
    $oldIban = $this->beneficiary->iban;

    editBeneficiaryPage($this->beneficiary)
        ->fillForm(['iban' => SaudiIban::fromParts('80', '000000222222222222')])
        ->call('save')
        ->assertActionMounted('confirmBankChange')
        ->call('unmountAction')
        ->assertActionNotMounted('confirmBankChange');

    expect($this->beneficiary->refresh()->iban)->toBe($oldIban)
        ->and(AuditLog::query()->exists())->toBeFalse();
});

test('إعادة كتابة الآيبان نفسه بمسافات أو حروف صغيرة لا تُعدّ تغييرًا', function (): void {
    editBeneficiaryPage($this->beneficiary)
        ->fillForm(['iban' => strtolower(implode(' ', str_split($this->beneficiary->iban, 4)))])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertActionNotMounted('confirmBankChange');

    expect(AuditLog::query()->exists())->toBeFalse();
});

test('تغيير عدة حقول بنكية معًا يُسجَّل في سطر تدقيق واحد بكل الحقول', function (): void {
    $newIban = SaudiIban::fromParts('05', '000000333333333333');

    editBeneficiaryPage($this->beneficiary)
        ->fillForm(['iban' => $newIban, 'bank_name' => 'مصرف الإنماء'])
        ->call('save')
        ->callMountedAction();

    expect(AuditLog::query()->where('action', UpdateBeneficiary::AUDIT_ACTION)->sole()->meta['changes'])
        ->toHaveKeys(['iban', 'bank_name'])
        ->iban->new->toBe($newIban)
        ->bank_name->old->toBe('مصرف الراجحي');
});

test('الخادم يرفض تغيير الحساب دون تأكيد حتى خارج اللوحة', function (): void {
    $oldIban = $this->beneficiary->iban;

    expect(fn () => app(UpdateBeneficiary::class)->handle(
        $this->supervisor,
        $this->beneficiary,
        ['iban' => SaudiIban::fromParts('80', '000000222222222222')],
    ))->toThrow(BankAccountChangeNotConfirmed::class);

    expect($this->beneficiary->refresh()->iban)->toBe($oldIban)
        ->and(AuditLog::query()->exists())->toBeFalse();
});
