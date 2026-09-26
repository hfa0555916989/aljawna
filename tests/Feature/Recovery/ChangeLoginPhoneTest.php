<?php

declare(strict_types=1);

use App\Actions\Recovery\ChangeLoginPhone;
use App\Actions\Recovery\SendPasswordResetLink;
use App\Filament\Pages\RecoveryLog;
use App\Filament\Pages\RecoveryRequests;
use App\Models\AuditLog;
use App\Models\PasswordResetRequest;
use App\Models\RecoveryLog as RecoveryLogEntry;
use App\Models\User;
use App\PasswordResetStatus;
use App\RecoveryLogAction;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel('admin');
});

test('تعديل الرقم بلا سبب يُرفض من الإجراء مباشرة ولا يتغير الرقم', function (): void {
    $user = User::factory()->create(['phone' => '+966511200001']);
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.change_phone'])->create();
    $request = phoneChangeRequest($user, $supervisor);

    expect(fn () => app(ChangeLoginPhone::class)->handle($supervisor, $request, '   ', '0511200002', '0511200002'))
        ->toThrow(ValidationException::class);

    expect($user->fresh()->phone)->toBe('+966511200001')
        ->and(RecoveryLogEntry::query()->count())->toBe(0);
});

test('رقم مكرر أو غير سعودي أو غير مؤكد يُرفض', function (): void {
    $user = User::factory()->create(['phone' => '+966511200011']);
    User::factory()->create(['phone' => '+966511200099']);
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.change_phone'])->create();
    $request = phoneChangeRequest($user, $supervisor);
    $action = app(ChangeLoginPhone::class);

    expect(fn () => $action->handle($supervisor, $request, 'فقد شريحته', '0511200099', '0511200099'))
        ->toThrow(ValidationException::class);

    expect(fn () => $action->handle($supervisor, $request, 'فقد شريحته', '12345', '12345'))
        ->toThrow(ValidationException::class);

    expect(fn () => $action->handle($supervisor, $request, 'فقد شريحته', '0511200012', '0511200013'))
        ->toThrow(ValidationException::class);

    expect($user->fresh()->phone)->toBe('+966511200011');
});

test('التعديل بسبب يحفظ السجل وينهي الجلسة والرابط التالي يذهب للرقم الجديد', function (): void {
    $user = User::factory()->create(['phone' => '+966511200021']);
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.change_phone'])->create();
    $request = phoneChangeRequest($user, $supervisor);

    $this->actingAs($user)->get('/dashboard')->assertOk();

    app(ChangeLoginPhone::class)->handle($supervisor, $request, 'الشريحة ضاعت', '0511200022', '0511200022');

    $log = RecoveryLogEntry::query()->first();
    $audit = AuditLog::query()->where('action', ChangeLoginPhone::AUDIT_ACTION)->first();

    expect($user->fresh()->phone)->toBe('+966511200022')
        ->and($log)->not->toBeNull()
        ->and($log->action)->toBe(RecoveryLogAction::PhoneChanged)
        ->and($log->old_phone)->toBe('+966511200021')
        ->and($log->new_phone)->toBe('+966511200022')
        ->and($log->reason)->toBe('الشريحة ضاعت')
        ->and($log->performed_by)->toBe($supervisor->id)
        ->and($audit)->not->toBeNull()
        ->and(json_encode($audit->meta))->not->toContain('511200022');

    $this->app['auth']->forgetGuards();
    $this->get('/dashboard')->assertRedirect(route('login'));

    $sent = app(SendPasswordResetLink::class)->handle($supervisor, $request->fresh(), false, null, null);

    expect($sent['whatsapp_url'])->toStartWith('https://wa.me/966511200022?text=');
});

test('سجل الاستعادة لا يُعدَّل ولا يُحذف', function (): void {
    $entry = RecoveryLogEntry::query()->create([
        'request_id' => phoneChangeRequest(User::factory()->create(), User::factory()->supervisor()->create())->id,
        'user_id' => User::factory()->create()->id,
        'performed_by' => User::factory()->supervisor()->create()->id,
        'action' => RecoveryLogAction::PhoneChanged,
        'old_phone' => '+966511200031',
        'new_phone' => '+966511200032',
        'reason' => 'سبب',
    ]);

    expect(fn () => $entry->update(['reason' => 'تغيير']))->toThrow(LogicException::class);
    expect(fn () => $entry->delete())->toThrow(LogicException::class);
    expect($entry->fresh()->reason)->toBe('سبب');
});

test('من لا يملك recovery.change_phone يُرفض بـ 403', function (): void {
    $user = User::factory()->create();
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $request = phoneChangeRequest($user, $supervisor);

    expect(fn () => app(ChangeLoginPhone::class)->handle($supervisor, $request, 'سبب', '0511200042', '0511200042'))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($supervisor);

    Livewire::test(RecoveryRequests::class)
        ->set('changeReason', 'سبب')
        ->set('newPhone', '0511200042')
        ->set('newPhoneConfirmation', '0511200042')
        ->call('changePhone', $request->id)
        ->assertForbidden();

    expect($user->fresh()->phone)->toBe($user->phone);
});

test('سجل الاستعادة للمدير فقط', function (): void {
    $owner = User::factory()->create(['full_name' => 'صاحب طلب الاستعادة الكريم', 'phone' => '+966511200051']);
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.change_phone'])->create([
        'full_name' => 'مشرف السجل الظاهر هنا',
    ]);
    app(ChangeLoginPhone::class)->handle($supervisor, phoneChangeRequest($owner, $supervisor), 'سبب ظاهر في السجل', '0511200052', '0511200052');

    $this->actingAs($supervisor)
        ->get('/admin/recovery/log')
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin/recovery/log')
        ->assertOk()
        ->assertSee('سبب ظاهر في السجل')
        ->assertSee('تعديل رقم الدخول')
        ->assertSee('مشرف السجل الظاهر هنا')
        ->assertSee('+966511200052');

    expect(RecoveryLog::canAccess())->toBeTrue();
});

test('المشرف لا يغيّر رقم دخول مشرف أو مدير ولو ملك كل صلاحيات الاستعادة (403)', function (User $owner): void {
    $originalPhone = $owner->phone;
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.other_number', 'recovery.change_phone'])->create();
    $request = phoneChangeRequest($owner, $supervisor);

    expect(fn () => app(ChangeLoginPhone::class)->handle($supervisor, $request, 'سبب', '0511200062', '0511200062'))
        ->toThrow(AuthorizationException::class, __('recovery.errors.admin_only'));

    $this->actingAs($supervisor);

    Livewire::test(RecoveryRequests::class)
        ->set('changeReason', 'سبب')
        ->set('newPhone', '0511200062')
        ->set('newPhoneConfirmation', '0511200062')
        ->call('changePhone', $request->id)
        ->assertForbidden();

    expect($owner->fresh()->phone)->toBe($originalPhone)
        ->and(RecoveryLogEntry::query()->count())->toBe(0);
})->with([
    'مدير' => fn () => User::factory()->admin()->create(),
    'مشرف' => fn () => User::factory()->supervisor()->withPermissions(['recovery.handle'])->create(),
]);

test('المدير يغيّر رقم دخول مشرف أو مدير آخر بسبب ويُسجَّل', function (User $owner): void {
    $admin = User::factory()->admin()->create();
    $request = phoneChangeRequest($owner, $admin);

    $this->actingAs($admin);

    Livewire::test(RecoveryRequests::class)
        ->set('changeReason', 'تحقق المدير من هويته حضوريًا')
        ->set('newPhone', '0511200072')
        ->set('newPhoneConfirmation', '0511200072')
        ->call('changePhone', $request->id)
        ->assertOk()
        ->assertHasNoErrors();

    expect($owner->fresh()->phone)->toBe('+966511200072')
        ->and(RecoveryLogEntry::query()->where('user_id', $owner->id)->where('performed_by', $admin->id)->exists())->toBeTrue();
})->with([
    'مدير آخر' => fn () => User::factory()->admin()->create(),
    'مشرف' => fn () => User::factory()->supervisor()->withPermissions(['recovery.handle'])->create(),
]);

function phoneChangeRequest(User $user, User $supervisor): PasswordResetRequest
{
    return PasswordResetRequest::query()->create([
        'user_id' => $user->id,
        'status' => PasswordResetStatus::Claimed,
        'claimed_by' => $supervisor->id,
        'claimed_until' => now()->addMinutes(15),
        'requested_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);
}
