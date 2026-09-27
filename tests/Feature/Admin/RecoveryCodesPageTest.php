<?php

declare(strict_types=1);

use App\Filament\Pages\RecoveryCodes;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| تجديد رموز الاسترداد بعد رمز TOTP حالي (docs/DECISIONS.md، T20)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

function userWithRecoveryCodes(string $role = 'supervisor'): User
{
    $user = User::factory()->{$role}()->create();
    AppAuthentication::make()->recoverable()->saveRecoveryCodes($user, ['old-code-1', 'old-code-2']);

    return $user->fresh();
}

test('كل صاحب تحقق من أدوار اللوحة يفتح الصفحة، ولو بلا أي صلاحية', function (string $role): void {
    $this->actingAs(userWithRecoveryCodes($role))
        ->get(RecoveryCodes::getUrl())
        ->assertOk()
        ->assertSee('تجديد رموز الاسترداد')
        ->assertSee('رموز الاسترداد المتبقية: 2');
})->with(['supervisor', 'admin']);

test('المبادر والزائر لا يصلان إليها', function (): void {
    $this->get(adminPath('two-factor/recovery-codes'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())
        ->get(adminPath('two-factor/recovery-codes'))
        ->assertNotFound();
});

test('الرابط في قائمة المستخدم باللوحة', function (): void {
    $this->actingAs(userWithRecoveryCodes())
        ->get(adminPath())
        ->assertOk()
        ->assertSee(RecoveryCodes::getUrl(), false);
});

test('الرمز الصحيح يصدر 8 رموز جديدة تُبطل السابقة وتُعرض مرة واحدة، ويُسجَّل في التدقيق', function (): void {
    $user = userWithRecoveryCodes();
    $provider = AppAuthentication::make()->recoverable();

    $this->actingAs($user);

    $page = Livewire::test(RecoveryCodes::class)
        ->set('code', $provider->getCurrentCode($user))
        ->call('regenerate')
        ->assertHasNoErrors()
        ->assertSee('صدرت رموز استرداد جديدة');

    $codes = $page->get('recoveryCodes');
    $user->refresh();

    expect($codes)->toHaveCount(8)
        ->and($provider->verifyRecoveryCode('old-code-1', $user))->toBeFalse()
        ->and($provider->verifyRecoveryCode($codes[0], $user))->toBeTrue();

    $audit = AuditLog::query()->where('action', RecoveryCodes::AUDIT_ACTION)->sole();

    expect($audit->actor_id)->toBe($user->id)
        ->and($audit->subject_id)->toBe($user->id)
        ->and($audit->meta)->toBe(['previous_remaining' => 2, 'count' => 8])
        ->and(json_encode($audit->meta))->not->toContain($codes[0]);
});

test('الرمز الخاطئ أو المُعاد استخدامه لا يغيّر شيئًا', function (): void {
    $user = userWithRecoveryCodes();
    $provider = AppAuthentication::make()->recoverable();
    $this->actingAs($user);

    Livewire::test(RecoveryCodes::class)
        ->set('code', '000000')
        ->call('regenerate')
        ->assertHasErrors(['code'])
        ->assertSet('recoveryCodes', []);

    $code = $provider->getCurrentCode($user);

    Livewire::test(RecoveryCodes::class)->set('code', $code)->call('regenerate')->assertHasNoErrors();
    Livewire::test(RecoveryCodes::class)->set('code', $code)->call('regenerate')->assertHasErrors(['code']);

    expect(AuditLog::query()->where('action', RecoveryCodes::AUDIT_ACTION)->count())->toBe(1);
});

test('الرموز الخاطئة محدودة لكل حساب، فلا يُخمَّن الرمز من جلسة مفتوحة', function (): void {
    $user = userWithRecoveryCodes();
    $provider = AppAuthentication::make()->recoverable();
    $this->actingAs($user);

    foreach (range(1, (int) config('security.two_factor.recovery_regeneration_max_attempts')) as $ignored) {
        Livewire::test(RecoveryCodes::class)->set('code', '000000')->call('regenerate')->assertHasErrors(['code']);
    }

    Cache::forget('filament.app_authentication_codes.'.md5((string) $user->getAppAuthenticationSecret()));

    Livewire::test(RecoveryCodes::class)
        ->set('code', $provider->getCurrentCode($user))
        ->call('regenerate')
        ->assertHasErrors(['code'])
        ->assertSee('محاولات كثيرة');

    expect(AuditLog::query()->where('action', RecoveryCodes::AUDIT_ACTION)->exists())->toBeFalse();
});
