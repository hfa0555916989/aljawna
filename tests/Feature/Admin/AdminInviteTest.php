<?php

declare(strict_types=1);

use App\Actions\Admin\InviteAdmin;
use App\Livewire\JoinAdmin;
use App\Models\AdminInvite;
use App\Models\AuditLog;
use App\Models\User;
use App\UserRole;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

test('الأمر ينشئ دعوة مدير صالحة 48 ساعة برمز مجزّأ', function (): void {
    Carbon::setTestNow('2026-09-27 12:00:00');

    $result = app(InviteAdmin::class)->handle('0511111101');

    $invite = $result['invite']->fresh();

    expect($invite->token_hash)->toBe(hash('sha256', $result['plain_token']))
        ->and($invite->token_hash)->not->toBe($result['plain_token'])
        ->and($invite->phone)->toBe('+966511111101')
        ->and($invite->expires_at->equalTo(now()->addHours(48)))->toBeTrue()
        ->and($result['join_url'])->toContain('/admin-join/'.$result['plain_token'])
        ->and($result['whatsapp_url'])->toMatch('#^https://wa\.me/966511111101\?text=.+$#');
});

test('أمر artisan admin:invite يطبع رابط الدعوة ورابط واتساب', function (): void {
    $this->artisan('admin:invite', ['phone' => '0511111102'])
        ->expectsOutputToContain('admin-join')
        ->assertSuccessful();

    expect(AdminInvite::query()->where('phone', '+966511111102')->exists())->toBeTrue();
});

test('رقم غير سعودي يُرفض دون إنشاء دعوة', function (): void {
    expect(fn () => app(InviteAdmin::class)->handle('+14155551234'))
        ->toThrow(ValidationException::class);

    expect(AdminInvite::query()->count())->toBe(0);

    $this->artisan('admin:invite', ['phone' => '+14155551234'])
        ->assertFailed();

    expect(AdminInvite::query()->count())->toBe(0);
});

test('الرمز ينتهي بعد 48 ساعة فلا يمكن استهلاكه', function (): void {
    Carbon::setTestNow('2026-09-27 12:00:00');

    $result = app(InviteAdmin::class)->handle('0511111103');

    Carbon::setTestNow(now()->addHours(48)->addSecond());

    Livewire::test(JoinAdmin::class, ['token' => $result['plain_token']])
        ->assertSet('invalid', true);

    expect(User::query()->where('phone', '+966511111103')->exists())->toBeFalse();
});

test('الرمز يُستهلك لمرة واحدة ولا يمكن إعادة استخدامه', function (): void {
    $result = app(InviteAdmin::class)->handle('0511111104');
    $token = $result['plain_token'];

    Livewire::test(JoinAdmin::class, ['token' => $token])
        ->set('full_name', 'سالم ماجد تركي العجاوني')
        ->set('password', 'Secretpass1')
        ->set('password_confirmation', 'Secretpass1')
        ->set('two_factor_code', pendingTwoFactorCode('join:admin:'.$token))
        ->call('join')
        ->assertHasNoErrors();

    expect($result['invite']->fresh()->accepted_at)->not->toBeNull();

    $user = User::query()->where('phone', '+966511111104')->sole();
    expect($user->role)->toBe(UserRole::Admin)
        ->and($user->is_active)->toBeTrue();

    Livewire::test(JoinAdmin::class, ['token' => $token])
        ->assertSet('invalid', true);

    expect(User::query()->where('phone', '+966511111104')->count())->toBe(1);
});

test('دعوة ثانية لنفس الرقم تُبطل الرمز السابق', function (): void {
    $first = app(InviteAdmin::class)->handle('0511111105');
    $second = app(InviteAdmin::class)->handle('0511111105');

    expect($first['invite']->fresh()->expires_at->isPast())->toBeTrue()
        ->and($second['invite']->fresh()->isUsable())->toBeTrue();

    Livewire::test(JoinAdmin::class, ['token' => $first['plain_token']])
        ->assertSet('invalid', true);

    expect(User::query()->where('phone', '+966511111105')->exists())->toBeFalse();
});

test('إنشاء حساب المدير يُسجَّل في سجل التدقيق', function (): void {
    $result = app(InviteAdmin::class)->handle('0511111106');

    Livewire::test(JoinAdmin::class, ['token' => $result['plain_token']])
        ->set('full_name', 'سالم ماجد تركي العجاوني')
        ->set('password', 'Secretpass1')
        ->set('password_confirmation', 'Secretpass1')
        ->set('two_factor_code', pendingTwoFactorCode('join:admin:'.$result['plain_token']))
        ->call('join')
        ->assertHasNoErrors();

    expect(AuditLog::query()->where('action', 'admin.invited')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'admin.joined')->exists())->toBeTrue();
});
