<?php

declare(strict_types=1);

use App\Actions\Recovery\ClaimPasswordReset;
use App\Actions\Recovery\CompletePasswordReset;
use App\Actions\Recovery\RequestPasswordReset;
use App\Actions\Recovery\SendPasswordResetLink;
use App\Actions\Supervisors\DeleteSupervisor;
use App\Actions\Supervisors\DemoteAdmin;
use App\Actions\Supervisors\SetSupervisorActive;
use App\Actions\Supervisors\UpdateSupervisorContact;
use App\Actions\Supervisors\UpdateSupervisorPermissions;
use App\Actions\Users\SetInitiatorActive;
use App\Livewire\Auth\Login;
use App\Models\User;
use App\PermissionKey;
use App\UserRole;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| لا طريق لمشرف إلى حساب مدير، ولا لتجاوز التحقق بخطوتين (T20 #7)
|--------------------------------------------------------------------------
| مراجعة مجمَّعة لكل إجراءات الإدارة والاستعادة من المهام السابقة: مشرف يملك كل
| الصلاحيات القابلة للمنح يحاول كل طريق على حساب مدير.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);

    $this->admin = User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'Admin-pass-1']);
    $this->supervisor = User::factory()->supervisor()->withPermissions(PermissionKey::values())->create();
});

test('إجراءات إدارة المشرفين كلها ترفض المدير هدفًا', function (Closure $attempt): void {
    expect(fn () => $attempt($this->supervisor, $this->admin))->toThrow(AuthorizationException::class);

    $admin = $this->admin->fresh();

    expect($admin->role)->toBe(UserRole::Admin)
        ->and($admin->is_active)->toBeTrue();
})->with([
    'تعديل الصلاحيات' => [fn (User $actor, User $admin) => app(UpdateSupervisorPermissions::class)->handle($actor, $admin, [])],
    'التعطيل' => [fn (User $actor, User $admin) => app(SetSupervisorActive::class)->handle($actor, $admin, false)],
    'الحذف' => [fn (User $actor, User $admin) => app(DeleteSupervisor::class)->handle($actor, $admin)],
    'تخفيض الدور' => [fn (User $actor, User $admin) => app(DemoteAdmin::class)->handle($actor, $admin)],
    'تعطيل كمبادر' => [fn (User $actor, User $admin) => app(SetInitiatorActive::class)->handle($actor, $admin, false, 'سبب')],
    'خيار الظهور' => [fn (User $actor, User $admin) => app(UpdateSupervisorContact::class)->handle($actor, $admin, true)],
]);

test('صفحة تعديل المشرف لا تفتح حساب مدير', function (): void {
    $this->actingAs($this->supervisor)
        ->get(adminPath('supervisors/'.$this->admin->id.'/edit'))
        ->assertNotFound();

    $this->actingAs($this->supervisor)
        ->put(route('admin.supervisors.permissions.update', $this->admin), ['permissions' => []])
        ->assertForbidden();
});

test('طلب استعادة لحساب المدير لا يعالجه مشرف ولو ملك كل صلاحيات الاستعادة', function (): void {
    $request = app(RequestPasswordReset::class)->handle('0512345678', '203.0.113.10')['request'];

    expect(fn () => app(ClaimPasswordReset::class)->handle($this->supervisor, $request))
        ->toThrow(AuthorizationException::class);

    $request->forceFill(['status' => 'claimed', 'claimed_by' => $this->supervisor->id, 'claimed_until' => now()->addMinutes(15)])->save();

    expect(fn () => app(SendPasswordResetLink::class)->handle($this->supervisor, $request->fresh(), true, 'سبب', '0599990000'))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($this->supervisor)
        ->get(adminPath('recovery'))
        ->assertOk()
        ->assertDontSee('512345678');
});

test('تغيير كلمة مرور حساب إداري بأي طريق لا يُسقط التحقق بخطوتين', function (): void {
    $target = User::factory()->supervisor()->create(['phone' => '+966511110000']);
    $request = app(RequestPasswordReset::class)->handle('0511110000', '203.0.113.11')['request'];

    app(ClaimPasswordReset::class)->handle($this->admin, $request);
    $url = rawurldecode(app(SendPasswordResetLink::class)->handle($this->admin, $request->fresh(), false, null, null)['whatsapp_url']);
    preg_match('#/reset/([A-Za-z0-9\-_]+)#', $url, $matches);

    app(CompletePasswordReset::class)->handle($matches[1], 'Brand-new-pass-1');

    Livewire::test(Login::class)
        ->set('phone', '0511110000')
        ->set('password', 'Brand-new-pass-1')
        ->call('login')
        ->assertNotSet('challengedUser', null)
        ->assertNoRedirect();

    expect(Auth::check())->toBeFalse()
        ->and($target->fresh()->hasTwoFactorEnabled())->toBeTrue();
});
