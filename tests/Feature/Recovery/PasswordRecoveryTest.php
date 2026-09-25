<?php

declare(strict_types=1);

use App\Actions\Recovery\CancelPasswordReset;
use App\Actions\Recovery\ClaimPasswordReset;
use App\Actions\Recovery\CompletePasswordReset;
use App\Actions\Recovery\RequestPasswordReset;
use App\Actions\Recovery\SendPasswordResetLink;
use App\Filament\Pages\RecoveryRequests;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\ResetPassword;
use App\Models\Beneficiary;
use App\Models\PasswordResetRequest;
use App\Models\PasswordResetToken;
use App\Models\Transfer;
use App\Models\User;
use App\PasswordResetStatus;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    RateLimiter::clear('recovery:ip:127.0.0.1');
    Filament::setCurrentPanel('admin');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('الرقم غير المسجّل لا ينشئ طلبًا', function (): void {
    Livewire::test(ForgotPassword::class)
        ->set('phone', '0511000000')
        ->call('submit')
        ->assertHasErrors(['phone']);

    expect(PasswordResetRequest::query()->count())->toBe(0);
});

test('الرقم المسجّل ينشئ طلبًا ويعرض أزرار المشرفين بلا نص الرقم', function (): void {
    $user = User::factory()->create(['phone' => '+966511002001']);
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create([
        'phone' => '+966511002002',
        'show_contact' => true,
    ]);

    Livewire::test(ForgotPassword::class)
        ->set('phone', '0511002001')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertSee(__('recovery.call'))
        ->assertSee(__('recovery.whatsapp'))
        ->assertSee('tel:'.$supervisor->phone, false)
        ->assertSee('https://wa.me/966511002002', false)
        ->assertDontSee('>'.$supervisor->phone.'<', false);

    expect(PasswordResetRequest::query()->where('user_id', $user->id)->where('status', PasswordResetStatus::Pending)->exists())->toBeTrue();
});

test('طلب نشط وفاصل 10 دقائق وحد الـ IP', function (): void {
    $user = User::factory()->create(['phone' => '+966511002003']);

    app(RequestPasswordReset::class)->handle('0511002003', '10.0.0.1');

    expect(fn () => app(RequestPasswordReset::class)->handle('0511002003', '10.0.0.2'))
        ->toThrow(ValidationException::class);

    $request = PasswordResetRequest::query()->first();
    $request->forceFill(['status' => PasswordResetStatus::Cancelled])->save();

    expect(fn () => app(RequestPasswordReset::class)->handle('0511002003', '10.0.0.2'))
        ->toThrow(ValidationException::class);

    Carbon::setTestNow(now()->addMinutes(10));

    app(RequestPasswordReset::class)->handle('0511002003', '10.0.0.2');

    config(['security.recovery.max_per_ip_per_hour' => 1]);
    RateLimiter::clear('recovery:ip:10.1.1.1');
    User::factory()->create(['phone' => '+966511002004']);
    $third = User::factory()->create(['phone' => '+966511002005']);
    app(RequestPasswordReset::class)->handle('0511002004', '10.1.1.1');

    expect(fn () => app(RequestPasswordReset::class)->handle($third->phone, '10.1.1.1'))
        ->toThrow(ValidationException::class);
});

test('حجز الاستلام 15 دقيقة يمنع مشرفًا آخر', function (): void {
    $user = User::factory()->create();
    $first = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $second = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $request = PasswordResetRequest::query()->create([
        'user_id' => $user->id,
        'status' => PasswordResetStatus::Pending,
        'requested_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);

    app(ClaimPasswordReset::class)->handle($first, $request);

    expect(fn () => app(ClaimPasswordReset::class)->handle($second, $request->fresh()))
        ->toThrow(AuthorizationException::class);

    Carbon::setTestNow(now()->addMinutes(15)->addSecond());

    app(ClaimPasswordReset::class)->handle($second, $request->fresh());

    expect($request->fresh()->claimed_by)->toBe($second->id);
});

test('شاشة الاستعادة لا تعرض بيانات الحوالات', function (): void {
    $owner = User::factory()->create(['full_name' => 'سالم ماجد تركي العجاوني', 'phone' => '+966511002010']);
    $beneficiary = Beneficiary::factory()->approved()->create(['display_name' => 'مستفيد سري للاختبار']);
    Transfer::factory()->create([
        'user_id' => $owner->id,
        'beneficiary_id' => $beneficiary->id,
        'amount' => '4321.77',
    ]);
    PasswordResetRequest::query()->create([
        'user_id' => $owner->id,
        'status' => PasswordResetStatus::Pending,
        'requested_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();

    $this->actingAs($supervisor);

    Livewire::test(RecoveryRequests::class)
        ->assertSee('سالم ماجد تركي العجاوني')
        ->assertSee('+966511002010')
        ->assertDontSee('4321.77')
        ->assertDontSee('مستفيد سري للاختبار')
        ->assertDontSee('receipts/');
});

test('الرمز مجزّأ ولمرة واحدة وينتهي بعد 30 دقيقة والجديد يُبطل السابق', function (): void {
    $user = User::factory()->create(['phone' => '+966511002020']);
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.other_number'])->create();
    $request = PasswordResetRequest::query()->create([
        'user_id' => $user->id,
        'status' => PasswordResetStatus::Claimed,
        'claimed_by' => $supervisor->id,
        'claimed_until' => now()->addMinutes(15),
        'requested_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);

    $first = app(SendPasswordResetLink::class)->handle($supervisor, $request, false, null, null);
    $old = tokenFromRecovery($first['whatsapp_url']);

    expect($first['whatsapp_url'])->toMatch('#^https://wa\.me/966511002020\?text=.+$#')
        ->and(rawurldecode(substr($first['whatsapp_url'], strpos($first['whatsapp_url'], '?text=') + 6)))->toContain('لا تشاركه مع أحد')
        ->and($first['token']->token_hash)->toBe(hash('sha256', $old))
        ->and(strlen(rawRecoveryToken($old)))->toBe(32);

    app(ClaimPasswordReset::class)->handle($supervisor, $request->fresh());

    $second = app(SendPasswordResetLink::class)->handle($supervisor, $request->fresh(), false, null, null);
    $current = tokenFromRecovery($second['whatsapp_url']);

    Livewire::test(ResetPassword::class, ['token' => $old])
        ->assertSet('invalid', false)
        ->set('password', 'Newpassword1')
        ->set('password_confirmation', 'Newpassword1')
        ->call('resetPassword');

    expect(PasswordResetToken::query()->where('token_hash', hash('sha256', $old))->first()?->isUsable())->toBeFalse();

    Livewire::test(ResetPassword::class, ['token' => $current])
        ->set('password', 'Newpassword2')
        ->set('password_confirmation', 'Newpassword2')
        ->call('resetPassword')
        ->assertHasNoErrors();

    expect($user->fresh()->password)->not->toBeNull();
});

test('الرمز ينتهي بعد 30 دقيقة', function (): void {
    Carbon::setTestNow('2026-09-25 10:00:00');
    $user = User::factory()->create();
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $sent = app(SendPasswordResetLink::class)->handle($supervisor, claimedRequest($user, $supervisor), false, null, null);
    $token = tokenFromRecovery($sent['whatsapp_url']);

    Carbon::setTestNow(now()->addMinutes(30)->addSecond());

    Livewire::test(ResetPassword::class, ['token' => $token])
        ->set('password', 'Newpassword1')
        ->set('password_confirmation', 'Newpassword1')
        ->call('resetPassword')
        ->assertHasErrors('form')
        ->assertSet('invalid', true);
});

test('رقم مختلف بلا سبب يُرفض وبسبب يُقبل لمن يملك الصلاحية', function (): void {
    $user = User::factory()->create();
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $request = claimedRequest($user, $supervisor);

    expect(fn () => app(SendPasswordResetLink::class)->handle($supervisor, $request, true, 'سبب', '0511002099'))
        ->toThrow(AuthorizationException::class);

    $allowed = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.other_number'])->create();
    $request->forceFill(['claimed_by' => $allowed->id, 'claimed_until' => now()->addMinutes(15)])->save();

    expect(fn () => app(SendPasswordResetLink::class)->handle($allowed, $request->fresh(), true, '   ', '0511002099'))
        ->toThrow(ValidationException::class);

    $sent = app(SendPasswordResetLink::class)->handle($allowed, $request->fresh(), true, 'الرقم الآخر لواتساب الأهل', '0511002099');

    expect($sent['whatsapp_url'])->toStartWith('https://wa.me/966511002099?text=');
});

test('تغيير كلمة المرور ينهي الجلسة القديمة', function (): void {
    $user = User::factory()->create();
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $request = claimedRequest($user, $supervisor);
    $sent = app(SendPasswordResetLink::class)->handle($supervisor, $request, false, null, null);
    $token = tokenFromRecovery($sent['whatsapp_url']);

    $this->actingAs($user)->get('/dashboard')->assertOk();

    app(CompletePasswordReset::class)->handle($token, 'Newpassword1');
    $this->app['auth']->forgetGuards();

    $this->get('/dashboard')->assertRedirect(route('login'));
});

test('الأمر يغلق الطلب بعد 24 ساعة', function (): void {
    Carbon::setTestNow('2026-09-25 08:00:00');
    $request = PasswordResetRequest::query()->create([
        'user_id' => User::factory()->create()->id,
        'status' => PasswordResetStatus::Pending,
        'requested_ip' => '127.0.0.1',
        'expires_at' => now()->addHours(24),
    ]);

    Carbon::setTestNow(now()->addHours(24)->addSecond());
    Artisan::call('recovery:expire');

    expect($request->fresh()->status)->toBe(PasswordResetStatus::Expired);
});

test('من لا يملك recovery.handle يُرفض بـ 403', function (): void {
    $this->actingAs(User::factory()->supervisor()->withPermissions(['users.view'])->create())
        ->get('/admin/recovery')
        ->assertForbidden();
});

test('كل محاولة تُحتسب في حد IP قبل أي فحص، بما فيها الرقم غير المسجّل', function (): void {
    config(['security.recovery.max_per_ip_per_hour' => 2]);
    RateLimiter::clear('recovery:ip:10.2.2.2');
    User::factory()->create(['phone' => '+966511002030']);

    foreach (['0511009991', '0511009992'] as $unregistered) {
        expect(fn () => app(RequestPasswordReset::class)->handle($unregistered, '10.2.2.2'))
            ->toThrow(ValidationException::class, __('recovery.errors.unregistered'));
    }

    expect(fn () => app(RequestPasswordReset::class)->handle('0511009993', '10.2.2.2'))
        ->toThrow(ValidationException::class, __('recovery.errors.ip_limit'))
        ->and(fn () => app(RequestPasswordReset::class)->handle('0511002030', '10.2.2.2'))
        ->toThrow(ValidationException::class, __('recovery.errors.ip_limit'));

    expect(PasswordResetRequest::query()->count())->toBe(0);
});

test('يُعاد استلام الطلب بعد إرسال رابطه، والرابط الجديد يُبطل السابق، والحجز يبقى لصاحبه 15 دقيقة', function (): void {
    $user = User::factory()->create(['phone' => '+966511002040']);
    $first = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $second = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $request = pendingRecoveryRequest($user);

    app(ClaimPasswordReset::class)->handle($first, $request);
    $old = tokenFromRecovery(app(SendPasswordResetLink::class)->handle($first, $request->fresh(), false, null, null)['whatsapp_url']);

    expect($request->fresh()->status)->toBe(PasswordResetStatus::LinkSent)
        ->and(fn () => app(ClaimPasswordReset::class)->handle($second, $request->fresh()))
        ->toThrow(AuthorizationException::class);

    Carbon::setTestNow(now()->addMinutes(15)->addSecond());

    app(ClaimPasswordReset::class)->handle($second, $request->fresh());

    expect($request->fresh()->status)->toBe(PasswordResetStatus::Claimed)
        ->and($request->fresh()->claimed_by)->toBe($second->id);

    $current = tokenFromRecovery(app(SendPasswordResetLink::class)->handle($second, $request->fresh(), false, null, null)['whatsapp_url']);

    expect(fn () => app(CompletePasswordReset::class)->handle($old, 'Newpassword1'))
        ->toThrow(ValidationException::class);

    app(CompletePasswordReset::class)->handle($current, 'Newpassword1');

    expect($request->fresh()->status)->toBe(PasswordResetStatus::Completed);
});

test('إعادة الاستلام وحدها لا تُبطل الرابط المرسل قبل إصدار رابط جديد', function (): void {
    $user = User::factory()->create();
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle'])->create();
    $request = claimedRequest($user, $supervisor);
    $token = tokenFromRecovery(app(SendPasswordResetLink::class)->handle($supervisor, $request, false, null, null)['whatsapp_url']);

    app(ClaimPasswordReset::class)->handle($supervisor, $request->fresh());
    app(CompletePasswordReset::class)->handle($token, 'Newpassword1');

    expect($request->fresh()->status)->toBe(PasswordResetStatus::Completed);
});

test('المشرف لا يعالج طلب استعادة لحساب مشرف أو مدير (403) ولا يراه في القائمة', function (User $owner): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['recovery.handle', 'recovery.other_number'])->create();
    $request = pendingRecoveryRequest($owner);

    expect($supervisor->can('handle', $request))->toBeFalse()
        ->and(fn () => app(ClaimPasswordReset::class)->handle($supervisor, $request))
        ->toThrow(AuthorizationException::class, __('recovery.errors.admin_only'))
        ->and(fn () => app(CancelPasswordReset::class)->handle($supervisor, $request))
        ->toThrow(AuthorizationException::class, __('recovery.errors.admin_only'));

    $request->forceFill([
        'status' => PasswordResetStatus::Claimed,
        'claimed_by' => $supervisor->id,
        'claimed_until' => now()->addMinutes(15),
    ])->save();

    expect(fn () => app(SendPasswordResetLink::class)->handle($supervisor, $request->fresh(), false, null, null))
        ->toThrow(AuthorizationException::class, __('recovery.errors.admin_only'))
        ->and(fn () => app(SendPasswordResetLink::class)->handle($supervisor, $request->fresh(), true, 'سبب', '0511002098'))
        ->toThrow(AuthorizationException::class, __('recovery.errors.admin_only'));

    $this->actingAs($supervisor);

    Livewire::test(RecoveryRequests::class)
        ->assertDontSee($owner->phone)
        ->call('claim', $request->id)
        ->assertForbidden();

    Livewire::test(RecoveryRequests::class)
        ->call('cancel', $request->id)
        ->assertForbidden();

    Livewire::test(RecoveryRequests::class)
        ->set('destination', 'other')
        ->set('reason', 'سبب')
        ->set('otherPhone', '0511002098')
        ->call('send', $request->id)
        ->assertForbidden();

    expect($request->fresh()->status)->toBe(PasswordResetStatus::Claimed)
        ->and(PasswordResetToken::query()->count())->toBe(0);
})->with([
    'مدير' => fn () => User::factory()->admin()->create(),
    'مشرف' => fn () => User::factory()->supervisor()->withPermissions(['recovery.handle'])->create(),
]);

test('المدير يستلم ويرسل رابط حساب مشرف أو مدير آخر، ولو إلى رقم مختلف', function (User $owner): void {
    $admin = User::factory()->admin()->create();
    $request = pendingRecoveryRequest($owner);

    $this->actingAs($admin);

    Livewire::test(RecoveryRequests::class)
        ->assertSee($owner->phone)
        ->call('claim', $request->id)
        ->assertOk();

    expect($request->fresh()->isClaimedBy($admin))->toBeTrue();

    Livewire::test(RecoveryRequests::class)
        ->set('destination', 'other')
        ->set('reason', 'فقد الجوال وتحقق المدير من هويته')
        ->set('otherPhone', '0511002097')
        ->call('send', $request->id)
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(PasswordResetStatus::LinkSent)
        ->and(PasswordResetToken::query()->where('request_id', $request->id)->value('sent_to_phone'))->toBe('+966511002097');
})->with([
    'مدير آخر' => fn () => User::factory()->admin()->create(),
    'مشرف' => fn () => User::factory()->supervisor()->withPermissions(['recovery.handle'])->create(),
]);

function pendingRecoveryRequest(User $owner): PasswordResetRequest
{
    return PasswordResetRequest::query()->create([
        'user_id' => $owner->id,
        'status' => PasswordResetStatus::Pending,
        'requested_ip' => '127.0.0.1',
        'expires_at' => now()->addDay(),
    ]);
}

function claimedRequest(User $user, User $supervisor): PasswordResetRequest
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

function tokenFromRecovery(string $url): string
{
    $text = rawurldecode(substr($url, strpos($url, '?text=') + 6));
    preg_match('#/reset/([A-Za-z0-9\-_]+)#', $text, $matches);

    return $matches[1];
}

function rawRecoveryToken(string $token): string
{
    $padded = $token.str_repeat('=', (4 - strlen($token) % 4) % 4);

    return base64_decode(strtr($padded, '-_', '+/'), true) ?: '';
}
