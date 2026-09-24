<?php

declare(strict_types=1);

use App\Actions\Beneficiaries\UpdateBeneficiary;
use App\Models\Beneficiary;
use App\Models\User;
use App\Support\SaudiIban;
use Database\Seeders\PermissionSeeder;

/*
|--------------------------------------------------------------------------
| تنبيه المدير في لوحته بتغيير الحسابات البنكية (T05 — §12.10)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->admin()->create();
    $this->manager = User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create([
        'full_name' => 'ماجد تركي نايف الشهري',
    ]);
    $this->beneficiary = Beneficiary::factory()->approved()->create(['display_name' => 'سالم ماجد تركي العجاوني']);
    $this->oldIban = $this->beneficiary->iban;
    $this->newIban = SaudiIban::fromParts('80', '000000444444444444');
});

function changeIban(User $actor, Beneficiary $beneficiary, string $iban): void
{
    app(UpdateBeneficiary::class)->handle($actor, $beneficiary, ['iban' => $iban], bankChangeConfirmed: true);
}

test('المدير يرى تنبيهًا ظاهرًا بالتغيير ومن غيّر والقيمة القديمة والجديدة', function (): void {
    changeIban($this->manager, $this->beneficiary, $this->newIban);

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertOk()
        ->assertSee('تنبيه: تغيّرت حسابات بنكية لمستفيدين')
        ->assertSee('ماجد تركي نايف الشهري غيّر الحساب البنكي لـ «سالم ماجد تركي العجاوني»')
        ->assertSee($this->oldIban)
        ->assertSee($this->newIban);
});

test('لا تنبيه للمدير دون تغييرات حديثة', function (): void {
    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('تنبيه: تغيّرت حسابات بنكية لمستفيدين');
});

test('التنبيه للمدير فقط وليس لمن يملك beneficiaries.manage', function (): void {
    changeIban($this->manager, $this->beneficiary, $this->newIban);

    $this->actingAs($this->manager)
        ->get('/admin')
        ->assertOk()
        ->assertDontSee('تنبيه: تغيّرت حسابات بنكية لمستفيدين');
});

test('التغيير الأقدم من مدة التنبيه لا يظهر', function (): void {
    config(['security.bank_account_changes.alert_days' => 14]);

    $this->travelTo(now()->subDays(15), fn () => changeIban($this->manager, $this->beneficiary, $this->newIban));

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertDontSee('تنبيه: تغيّرت حسابات بنكية لمستفيدين');
});
