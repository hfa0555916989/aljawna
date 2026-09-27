<?php

declare(strict_types=1);

use App\Actions\Admin\CompleteAdminPasswordReset;
use App\Actions\Admin\IssueAdminResetLink;
use App\Actions\Auth\ResetTwoFactor;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\ResetAdminPassword;
use App\Models\AdminPasswordReset;
use App\Models\AuditLog;
use App\Models\TwoFactorSetupLink;
use App\Models\User;
use App\Support\SessionEpoch;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| أوامر الطوارئ من سطر الأوامر فقط: admin:reset-link وadmin:reset-2fa (docs/DECISIONS.md)
|--------------------------------------------------------------------------
*/

test('admin:reset-link يطبع رابطًا صالحًا لمرة واحدة ويُسجَّل في التدقيق', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678']);

    $this->artisan('admin:reset-link', ['phone' => '0512345678'])
        ->expectsOutputToContain('/admin-reset/')
        ->expectsOutputToContain('https://wa.me/966512345678')
        ->assertSuccessful();

    $reset = AdminPasswordReset::query()->sole();
    $audit = AuditLog::query()->where('action', IssueAdminResetLink::AUDIT_ACTION)->sole();

    expect($reset->user_id)->toBe($admin->id)
        ->and($reset->isUsable())->toBeTrue()
        ->and($reset->expires_at->diffInMinutes(now(), true))->toBeGreaterThan(29)->toBeLessThanOrEqual(30)
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->subject_id)->toBe($admin->id)
        ->and($audit->meta)->toMatchArray(['issued_via' => 'cli']);
});

test('admin:reset-link يرفض من ليس مديرًا فعّالًا دون إصدار رابط', function (?string $role, bool $active, string $message): void {
    if ($role !== null) {
        User::factory()->state(['role' => $role, 'is_active' => $active])->create(['phone' => '+966512345678']);
    }

    $this->artisan('admin:reset-link', ['phone' => '0512345678'])
        ->expectsOutputToContain($message)
        ->assertFailed();

    expect(AdminPasswordReset::query()->count())->toBe(0);
})->with([
    'رقم غير مسجّل' => [null, true, 'لا يوجد مدير مسجّل بهذا الرقم.'],
    'مشرف' => ['supervisor', true, 'لا يوجد مدير مسجّل بهذا الرقم.'],
    'مبادر' => ['user', true, 'لا يوجد مدير مسجّل بهذا الرقم.'],
    'مدير معطَّل' => ['admin', false, 'حساب هذا المدير معطَّل.'],
]);

test('الرمز الخام لا يُخزَّن، وإصدار رابط جديد يُبطل السابق', function (): void {
    User::factory()->admin()->create(['phone' => '+966512345678']);

    $first = app(IssueAdminResetLink::class)->handle('0512345678');
    $second = app(IssueAdminResetLink::class)->handle('0512345678');

    expect(AdminPasswordReset::query()->where('token_hash', $first['plain_token'])->exists())->toBeFalse()
        ->and($first['reset']->fresh()?->isUsable())->toBeFalse()
        ->and($second['reset']->fresh()?->isUsable())->toBeTrue();
});

test('الرابط يعيّن كلمة المرور مرة واحدة وينهي الجلسات، ويبقى التحقق بخطوتين مطلوبًا', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678']);
    $secret = $admin->getAppAuthenticationSecret();
    $epoch = SessionEpoch::current($admin);
    $result = app(IssueAdminResetLink::class)->handle('0512345678');

    $this->get(route('admin.password.reset', ['token' => $result['plain_token']]))->assertOk();

    Livewire::test(ResetAdminPassword::class, ['token' => $result['plain_token']])
        ->set('password', 'N3w-secure-pass')
        ->set('password_confirmation', 'N3w-secure-pass')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $admin->refresh();

    expect(Hash::check('N3w-secure-pass', $admin->password))->toBeTrue()
        ->and($admin->getAppAuthenticationSecret())->toBe($secret)
        ->and(SessionEpoch::current($admin))->toBe($epoch + 1)
        ->and(AuditLog::query()->where('action', CompleteAdminPasswordReset::AUDIT_ACTION)->where('subject_id', $admin->id)->exists())->toBeTrue();

    Livewire::test(ResetAdminPassword::class, ['token' => $result['plain_token']])
        ->assertSet('invalid', true)
        ->set('password', 'Another-pass-1')
        ->set('password_confirmation', 'Another-pass-1')
        ->call('resetPassword')
        ->assertHasErrors(['form']);
});

test('الرابط ينتهي بعد 30 دقيقة', function (): void {
    User::factory()->admin()->create(['phone' => '+966512345678']);
    $result = app(IssueAdminResetLink::class)->handle('0512345678');

    $this->travel(31)->minutes();

    Livewire::test(ResetAdminPassword::class, ['token' => $result['plain_token']])
        ->assertSet('invalid', true)
        ->assertSee('رابط التعيين غير صالح أو استُخدم من قبل.');
});

test('admin:reset-2fa يحذف السر ورموز الاسترداد وينهي الجلسات ويُسجَّل في التدقيق', function (): void {
    $supervisor = User::factory()->supervisor()->create(['phone' => '+966512345678']);
    AppAuthentication::make()->saveRecoveryCodes($supervisor, ['aaa-bbb']);
    $epoch = SessionEpoch::current($supervisor);

    $this->artisan('admin:reset-2fa', ['phone' => '0512345678'])
        ->expectsConfirmation('سيُحذف إعداد التحقق بخطوتين ورموز الاسترداد لهذا المستخدم وتنتهي كل جلساته. متابعة؟', 'yes')
        ->expectsOutputToContain('لن يستطيع الدخول بكلمة المرور وحدها')
        ->expectsOutputToContain(url('/two-factor/setup/'))
        ->expectsOutputToContain('https://wa.me/966512345678?text=')
        ->assertSuccessful();

    $supervisor->refresh();
    $audit = AuditLog::query()->where('action', ResetTwoFactor::AUDIT_ACTION)->sole();

    expect($supervisor->hasTwoFactorEnabled())->toBeFalse()
        ->and($supervisor->getAppAuthenticationRecoveryCodes())->toBeNull()
        ->and(SessionEpoch::current($supervisor))->toBe($epoch + 1)
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->subject_id)->toBe($supervisor->id)
        ->and($audit->meta)->toBe(['issued_via' => 'cli', 'was_enabled' => true, 'setup_link_id' => TwoFactorSetupLink::query()->sole()->id]);
});

test('بعد admin:reset-2fa لا يدخل صاحب دور اللوحة بكلمة المرور وحدها (T20)', function (): void {
    User::factory()->admin()->create(['phone' => '+966512345678', 'password' => 'S3cure-pass']);

    $this->artisan('admin:reset-2fa', ['phone' => '0512345678', '--force' => true])->assertSuccessful();

    Livewire::test(Login::class)
        ->set('phone', '0512345678')
        ->set('password', 'S3cure-pass')
        ->call('login')
        ->assertHasErrors(['phone'])
        ->assertNoRedirect();

    expect(auth()->check())->toBeFalse();
});

test('admin:reset-2fa للمبادر لا يصدر رابط إعداد لأنه لا يستخدم التحقق', function (): void {
    User::factory()->create(['phone' => '+966512345678']);

    $this->artisan('admin:reset-2fa', ['phone' => '0512345678', '--force' => true])
        ->expectsOutputToContain('أُعيد ضبط التحقق بخطوتين وأُنهيت جلسات المستخدم.')
        ->doesntExpectOutputToContain('/two-factor/setup/')
        ->assertSuccessful();

    expect(TwoFactorSetupLink::query()->exists())->toBeFalse();
});

test('admin:reset-2fa لا يغيّر شيئًا دون تأكيد أو لرقم غير مسجّل', function (): void {
    $admin = User::factory()->admin()->create(['phone' => '+966512345678']);

    $this->artisan('admin:reset-2fa', ['phone' => '0512345678'])
        ->expectsConfirmation('سيُحذف إعداد التحقق بخطوتين ورموز الاسترداد لهذا المستخدم وتنتهي كل جلساته. متابعة؟', 'no')
        ->assertFailed();

    $this->artisan('admin:reset-2fa', ['phone' => '0598765432', '--force' => true])
        ->expectsOutputToContain('لا يوجد مستخدم مسجّل بهذا الرقم.')
        ->assertFailed();

    expect($admin->refresh()->hasTwoFactorEnabled())->toBeTrue()
        ->and(AuditLog::query()->where('action', ResetTwoFactor::AUDIT_ACTION)->exists())->toBeFalse();
});

test('لا مسار ويب يشغّل أوامر الطوارئ أو أي أمر artisan', function (): void {
    $routes = collect(app('router')->getRoutes()->getRoutes());

    expect($routes->filter(fn ($route): bool => str_contains($route->getActionName(), 'Artisan')
        || str_contains($route->uri(), 'reset-2fa')
        || str_contains($route->uri(), 'reset-link')
        || str_contains($route->uri(), 'terminal')
        || str_contains($route->uri(), 'console')))->toBeEmpty();

    foreach (['app', 'resources/views', 'routes/web.php'] as $path) {
        $files = is_dir(base_path($path))
            ? collect(File::allFiles(base_path($path)))->map->getPathname()
            : collect([base_path($path)]);

        foreach ($files as $file) {
            if (str_contains($file, 'Console/Commands')) {
                continue;
            }

            expect(file_get_contents($file))->not->toContain('Artisan::call')
                ->not->toContain('shell_exec(')
                ->not->toContain('Process::run');
        }
    }
});
