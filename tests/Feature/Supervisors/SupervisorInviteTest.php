<?php

declare(strict_types=1);

use App\Actions\Supervisors\InviteSupervisor;
use App\Actions\Supervisors\SetSupervisorActive;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Supervisors\Pages\ListSupervisors;
use App\Livewire\JoinSupervisor;
use App\Models\SupervisorInvite;
use App\Models\User;
use App\UserRole;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    $this->admin = User::factory()->admin()->create();
    Filament::setCurrentPanel('admin');
});

test('رمز الدعوة مجزّأ ولا يُخزَّن خامًا وطوله 32 بايت', function (): void {
    $result = app(InviteSupervisor::class)->handle($this->admin, '0511111001', ['users.view']);

    $token = tokenFromWhatsapp($result['whatsapp_url']);
    $invite = $result['invite']->fresh();

    expect($invite->token_hash)->toBe(hash('sha256', $token))
        ->and($invite->token_hash)->not->toBe($token)
        ->and(SupervisorInvite::query()->where('token_hash', $token)->exists())->toBeFalse()
        ->and(strlen(rawTokenBytes($token)))->toBe(32);
});

test('الرمز يُستهلك لمرة واحدة', function (): void {
    $result = app(InviteSupervisor::class)->handle($this->admin, '0511111002', ['users.view']);
    $token = tokenFromWhatsapp($result['whatsapp_url']);

    Livewire::test(JoinSupervisor::class, ['token' => $token])
        ->set('full_name', 'سالم ماجد تركي العجاوني')
        ->set('password', 'Secretpass1')
        ->set('password_confirmation', 'Secretpass1')
        ->call('join')
        ->assertHasNoErrors();

    expect($result['invite']->fresh()->accepted_at)->not->toBeNull();

    Livewire::test(JoinSupervisor::class, ['token' => $token])
        ->assertSet('invalid', true);

    expect(User::query()->where('phone', '+966511111002')->count())->toBe(1);
});

test('دعوة ثانية لنفس الرقم تُبطل الرمز السابق', function (): void {
    $first = app(InviteSupervisor::class)->handle($this->admin, '0511111008', ['users.view']);
    $oldToken = tokenFromWhatsapp($first['whatsapp_url']);

    $second = app(InviteSupervisor::class)->handle($this->admin, '0511111008', ['stats.view']);
    $newToken = tokenFromWhatsapp($second['whatsapp_url']);

    expect($first['invite']->fresh()->expires_at->isPast())->toBeTrue()
        ->and($second['invite']->fresh()->isUsable())->toBeTrue();

    Livewire::test(JoinSupervisor::class, ['token' => $oldToken])
        ->assertSet('invalid', true);

    expect(User::query()->where('phone', '+966511111008')->exists())->toBeFalse();

    Livewire::test(JoinSupervisor::class, ['token' => $newToken])
        ->set('full_name', 'سالم ماجد تركي العجاوني')
        ->set('password', 'Secretpass1')
        ->set('password_confirmation', 'Secretpass1')
        ->call('join')
        ->assertHasNoErrors();

    expect(User::query()->where('phone', '+966511111008')->count())->toBe(1);
});

test('الرمز ينتهي بعد 72 ساعة', function (): void {
    Carbon::setTestNow('2026-09-24 12:00:00');

    $result = app(InviteSupervisor::class)->handle($this->admin, '0511111003', ['users.view']);
    $token = tokenFromWhatsapp($result['whatsapp_url']);

    Carbon::setTestNow(now()->addHours(72)->addSecond());

    Livewire::test(JoinSupervisor::class, ['token' => $token])
        ->assertSet('invalid', true);

    expect(User::query()->where('role', UserRole::Supervisor)->where('phone', '+966511111003')->exists())->toBeFalse();
});

test('رابط واتساب بصيغة wa.me مع الرقم والنص المرمّزين', function (): void {
    $result = app(InviteSupervisor::class)->handle($this->admin, '0511111004', ['users.view']);
    $url = $result['whatsapp_url'];

    expect($url)->toMatch('#^https://wa\.me/966511111004\?text=.+$#');

    $text = rawurldecode(substr($url, strpos($url, '?text=') + 6));
    $token = tokenFromWhatsapp($url);

    expect($text)->toContain(route('supervisors.join', ['token' => $token]))
        ->and($url)->not->toContain(' ');
});

test('مشرف لا يدعو صلاحية لا يملكها', function (): void {
    $manager = User::factory()->supervisor()->withPermissions(['supervisors.manage', 'users.view'])->create();

    expect(fn () => app(InviteSupervisor::class)->handle($manager, '0511111005', ['settings.manage']))
        ->toThrow(AuthorizationException::class);

    expect(SupervisorInvite::query()->count())->toBe(0);
});

test('تعطيل المشرف يمنعه من الدخول فورًا', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view'])->create();

    $this->actingAs($supervisor)->get('/admin')->assertOk();

    app(SetSupervisorActive::class)->handle($this->admin, $supervisor, false);

    $this->actingAs($supervisor->fresh())->get('/admin')->assertForbidden();

    auth()->logout();

    Livewire::test(Login::class)
        ->fillForm([
            'phone' => $supervisor->phone,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['phone']);

    expect(auth()->check())->toBeFalse();
});

test('من لا يملك supervisors.manage لا يفتح إدارة المشرفين', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.view'])->create();

    $this->actingAs($supervisor)->get('/admin/supervisors')->assertForbidden();
});

test('الرقم في صفحة الانضمام يبقى رقم الدعوة', function (): void {
    $result = app(InviteSupervisor::class)->handle($this->admin, '0511111006', ['users.view']);
    $token = tokenFromWhatsapp($result['whatsapp_url']);

    Livewire::test(JoinSupervisor::class, ['token' => $token])
        ->set('phone', '0599999999')
        ->set('full_name', 'سالم ماجد تركي العجاوني')
        ->set('password', 'Secretpass1')
        ->set('password_confirmation', 'Secretpass1')
        ->call('join')
        ->assertHasNoErrors();

    $user = User::query()->where('role', UserRole::Supervisor)->where('full_name', 'سالم ماجد تركي العجاوني')->first();

    expect($user)->not->toBeNull()
        ->and($user->phone)->toBe('+966511111006')
        ->and($user->can('users.view'))->toBeTrue();
});

test('دعوة من صفحة الإدارة تولّد الرابط', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ListSupervisors::class)
        ->callAction('invite', [
            'phone' => '0511111007',
            'permissions' => ['users.view'],
        ])
        ->assertNotified();

    expect(SupervisorInvite::query()->where('phone', '+966511111007')->exists())->toBeTrue();
});

function tokenFromWhatsapp(string $url): string
{
    $text = rawurldecode(substr($url, strpos($url, '?text=') + 6));

    preg_match('#/join/([A-Za-z0-9\-_]+)#', $text, $matches);

    return $matches[1];
}

function rawTokenBytes(string $token): string
{
    $padded = $token.str_repeat('=', (4 - strlen($token) % 4) % 4);

    return base64_decode(strtr($padded, '-_', '+/'), true) ?: '';
}
