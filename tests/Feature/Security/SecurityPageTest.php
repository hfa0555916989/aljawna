<?php

declare(strict_types=1);

use App\Actions\Users\SetInitiatorActive;
use App\Filament\Pages\Security;
use App\Livewire\Auth\Login;
use App\Models\AuditLog;
use App\Models\LoginAttempt;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| تبويب الأمان وتعطيل الحسابات (T14 — docs/SPEC.md §4.4, §12.7, FR-22)
|--------------------------------------------------------------------------
| الصفحة لمن يملك security.view، والتعطيل/التفعيل لمن يملك users.suspend
| وبسبب محفوظ في سجل التدقيق. التعطيل ينهي الجلسات ويمنع الدخول فورًا.
*/

const SECURITY_PAGE = '/admin/security';

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');

    config(['security.suspicious.failed_logins' => ['threshold' => 2, 'hours' => 24]]);
});

function securityOfficer(): User
{
    return User::factory()->supervisor()->withPermissions(['security.view', 'users.suspend'])->create();
}

function suspiciousInitiator(array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    LoginAttempt::factory()->count(2)->create(['phone' => $user->phone]);

    return $user;
}

test('من لا يملك security.view يُرفض بـ 403 ولو ملك users.suspend', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['users.suspend'])->create();

    $this->actingAs($supervisor)->get(SECURITY_PAGE)->assertForbidden();

    expect(Security::canAccess())->toBeFalse();
});

test('المشرف الممنوح security.view يفتح الصفحة', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['security.view'])->create();

    $this->actingAs($supervisor)->get(SECURITY_PAGE)->assertOk()->assertSee(__('security.navigation'));
});

test('المدير يفتح الصفحة ضمنيًا', function (): void {
    $this->actingAs(User::factory()->admin()->create())->get(SECURITY_PAGE)->assertOk();
});

test('المبادر لا يصل إلى الصفحة ويُحوَّل إلى لوحته', function (): void {
    $this->actingAs(User::factory()->create())->get(SECURITY_PAGE)->assertRedirect(route('dashboard'));
});

test('المشرف المعطَّل يُرفض بـ 403 ولو ملك security.view', function (): void {
    $supervisor = User::factory()->supervisor()->inactive()->withPermissions(['security.view'])->create();

    $this->actingAs($supervisor)->get(SECURITY_PAGE)->assertForbidden();
});

test('الصفحة تعرض الحساب المشبوه وقاعدته ورقمه بالاتجاه LTR', function (): void {
    $user = suspiciousInitiator(['full_name' => 'سالم عبدالله محمد العجاوني']);

    Livewire::actingAs(securityOfficer())
        ->test(Security::class)
        ->assertSee('سالم عبدالله محمد العجاوني')
        ->assertSeeHtml('data-suspicious-account="'.$user->id.'"')
        ->assertSeeHtml('data-suspicious-rule="failed_logins"')
        ->assertSee(__('security.suspicious.rules.failed_logins', ['count' => 2, 'hours' => 24]));
});

test('سجل المحاولات يعرض المحاولات الأحدث أولًا', function (): void {
    $older = LoginAttempt::factory()->create(['phone' => '+966511100001']);
    $newer = LoginAttempt::factory()->succeeded()->create(['phone' => '+966511100002']);

    Livewire::actingAs(securityOfficer())
        ->test(Security::class)
        ->call('selectTab', 'attempts')
        ->assertSeeHtmlInOrder(['data-login-attempt="'.$newer->id.'"', 'data-login-attempt="'.$older->id.'"'])
        ->assertSee('+966511100002');
});

test('تبويب غير معروف يُتجاهل', function (): void {
    Livewire::actingAs(securityOfficer())
        ->test(Security::class)
        ->call('selectTab', 'nope')
        ->assertSet('tab', 'suspicious');
});

test('مرشّحات سجل التدقيق: الإجراء والمنفّذ والنظام والتاريخ', function (): void {
    $officer = securityOfficer();
    $other = User::factory()->supervisor()->create();

    $this->travelTo(now()->setDateTime(2026, 9, 20, 10, 0));
    $oldEntry = AuditLog::factory()->create(['action' => 'transfer.matched', 'actor_id' => $officer->id]);
    $this->travelTo(now()->setDateTime(2026, 9, 27, 10, 0));
    $matched = AuditLog::factory()->create(['action' => 'transfer.matched', 'actor_id' => $officer->id]);
    $byOther = AuditLog::factory()->create(['action' => 'permissions.updated', 'actor_id' => $other->id]);
    $bySystem = AuditLog::factory()->create(['action' => 'permissions.updated', 'actor_id' => null]);

    $page = Livewire::actingAs($officer)->test(Security::class)->call('selectTab', 'audit');

    $entryIds = fn (): array => $page->instance()->auditEntries->pluck('id')->all();

    expect($entryIds())->toEqualCanonicalizing([$oldEntry->id, $matched->id, $byOther->id, $bySystem->id]);

    $page->set('auditAction', 'transfer.matched');
    expect($entryIds())->toEqualCanonicalizing([$oldEntry->id, $matched->id]);

    $page->set('auditFrom', '2026-09-27');
    expect($entryIds())->toBe([$matched->id]);

    $page->set('auditAction', '')->set('auditActor', (string) $other->id);
    expect($entryIds())->toBe([$byOther->id]);

    $page->set('auditActor', Security::ACTOR_SYSTEM);
    expect($entryIds())->toBe([$bySystem->id]);

    $page->set('auditActor', '')->set('auditFrom', '')->set('auditTo', '2026-09-20');
    expect($entryIds())->toBe([$oldEntry->id]);

    $page->set('auditTo', 'not-a-date');
    expect($entryIds())->toHaveCount(4);
});

test('التعطيل بسبب يُطبَّق ويُسجَّل في التدقيق مع السبب', function (): void {
    $officer = securityOfficer();
    $user = suspiciousInitiator();

    Livewire::actingAs($officer)
        ->test(Security::class)
        ->call('startToggle', $user->id)
        ->set('toggleReason', '  محاولات دخول متكررة من أجهزة مختلفة  ')
        ->call('toggleActive', $user->id)
        ->assertHasNoErrors()
        ->assertSet('togglingId', null)
        ->assertSet('toggleReason', '');

    expect($user->fresh()->is_active)->toBeFalse();

    $audit = AuditLog::query()->where('action', SetInitiatorActive::AUDIT_SUSPENDED)->sole();

    expect($audit->actor_id)->toBe($officer->id)
        ->and($audit->subject_type)->toBe($user->getMorphClass())
        ->and($audit->subject_id)->toBe($user->id)
        ->and($audit->meta)->toBe(['reason' => 'محاولات دخول متكررة من أجهزة مختلفة']);
});

test('التعطيل دون سبب يُرفض ولا يغيّر الحساب', function (string $reason): void {
    $user = suspiciousInitiator();

    Livewire::actingAs(securityOfficer())
        ->test(Security::class)
        ->call('startToggle', $user->id)
        ->set('toggleReason', $reason)
        ->call('toggleActive', $user->id)
        ->assertHasErrors(['toggle_reason']);

    expect($user->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->count())->toBe(0);
})->with(['فارغ' => [''], 'مسافات فقط' => ['   ']]);

test('السبب الأطول من الحد يُرفض', function (): void {
    $user = suspiciousInitiator();

    expect(fn () => app(SetInitiatorActive::class)->handle(securityOfficer(), $user, false, str_repeat('س', SetInitiatorActive::REASON_MAX_LENGTH + 1)))
        ->toThrow(ValidationException::class);

    expect($user->fresh()->is_active)->toBeTrue();
});

test('من يملك security.view دون users.suspend يُرفض بـ 403 عند التعطيل', function (): void {
    $viewer = User::factory()->supervisor()->withPermissions(['security.view'])->create();
    $user = suspiciousInitiator();

    Livewire::actingAs($viewer)
        ->test(Security::class)
        ->assertDontSee(__('security.suspend'))
        ->set('toggleReason', 'سبب')
        ->call('toggleActive', $user->id)
        ->assertForbidden();

    expect($user->fresh()->is_active)->toBeTrue();
});

test('الإجراء يرفض التعطيل ممن لا يملك users.suspend ولو استُدعي مباشرة', function (): void {
    $viewer = User::factory()->supervisor()->withPermissions(['security.view'])->create();
    $user = User::factory()->create();

    expect(fn () => app(SetInitiatorActive::class)->handle($viewer, $user, false, 'سبب'))
        ->toThrow(AuthorizationException::class);

    expect($user->fresh()->is_active)->toBeTrue();
});

test('لا يُعطَّل عبر هذه الصفحة إلا حساب مبادر', function (string $role): void {
    $target = User::factory()->{$role}()->create();

    expect(fn () => app(SetInitiatorActive::class)->handle(securityOfficer(), $target, false, 'سبب'))
        ->toThrow(AuthorizationException::class, __('security.errors.not_initiator'));

    expect($target->fresh()->is_active)->toBeTrue();
})->with(['supervisor', 'admin']);

test('التعطيل ينهي جلسة المبادر المفتوحة فورًا', function (): void {
    $user = User::factory()->create();

    Auth::login($user);
    $this->get('/dashboard')->assertOk();
    $this->app['auth']->forgetGuards();

    app(SetInitiatorActive::class)->handle(securityOfficer(), $user, false, 'نشاط مشبوه');
    $this->app['auth']->forgetGuards();

    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('الحساب المعطَّل يُمنع من الدخول برسالة مناسبة', function (): void {
    $user = User::factory()->create(['phone' => '+966512300001', 'password' => 'S3cure-pass']);

    app(SetInitiatorActive::class)->handle(securityOfficer(), $user, false, 'نشاط مشبوه');

    Livewire::test(Login::class)
        ->set('phone', '0512300001')
        ->set('password', 'S3cure-pass')
        ->call('login')
        ->assertHasErrors(['phone'])
        ->assertSee(__('auth.inactive'));

    expect(Auth::check())->toBeFalse();
});

test('إعادة التفعيل بسبب تتيح الدخول وتُسجَّل في التدقيق', function (): void {
    $officer = securityOfficer();
    $user = User::factory()->inactive()->create(['phone' => '+966512300002', 'password' => 'S3cure-pass']);

    Livewire::actingAs($officer)
        ->test(Security::class)
        ->assertSeeHtml('data-suspended-account="'.$user->id.'"')
        ->call('startToggle', $user->id)
        ->set('toggleReason', 'تحقّقت الإدارة من هويته')
        ->call('toggleActive', $user->id)
        ->assertHasNoErrors()
        ->assertDontSeeHtml('data-suspended-account="'.$user->id.'"');

    expect($user->fresh()->is_active)->toBeTrue()
        ->and(AuditLog::query()->where('action', SetInitiatorActive::AUDIT_ACTIVATED)->sole()->meta)
        ->toBe(['reason' => 'تحقّقت الإدارة من هويته']);

    Auth::logout();

    Livewire::test(Login::class)
        ->set('phone', '0512300002')
        ->set('password', 'S3cure-pass')
        ->call('login')
        ->assertHasNoErrors();

    expect(Auth::id())->toBe($user->id);
});
