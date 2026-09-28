<?php

declare(strict_types=1);

use App\Mail\SystemAlert;
use App\Services\SystemAlerts;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| php artisan alerts:test: تنبيه تجريبي إلى ALERT_EMAIL (T21)
|--------------------------------------------------------------------------
*/

test('يرسل تنبيهًا تجريبيًا إلى ALERT_EMAIL وحده فورًا ودون منع التكرار', function (): void {
    Mail::fake();
    config(['monitoring.alert_email' => 'ops@example.test']);

    $this->artisan('alerts:test')->expectsOutputToContain('ops@example.test')->assertSuccessful();
    $this->artisan('alerts:test')->assertSuccessful();

    Mail::assertSent(SystemAlert::class, 2);
    Mail::assertSent(SystemAlert::class, fn (SystemAlert $mail): bool => $mail->alert === SystemAlerts::TEST
        && $mail->hasTo('ops@example.test')
        && count($mail->to) === 1);
    Mail::assertNothingQueued();

    expect(DB::table('system_alerts')->count())->toBe(0);
});

test('الرسالة التجريبية بلا بيانات شخصية ولا رابط اللوحة', function (): void {
    config(['monitoring.alert_email' => 'ops@example.test']);

    $rendered = (new SystemAlert(SystemAlerts::TEST, __('health.alerts.test.details', ['mailer' => 'smtp'])))->render();

    expect($rendered)->toContain('تنبيه تجريبي')
        ->and($rendered)->not->toContain((string) config('admin.path'))
        ->and($rendered)->not->toMatch('/\+9665\d{8}/');
});

test('يفشل بوضوح إن كان ALERT_EMAIL فارغًا ولا يرسل شيئًا', function (): void {
    Mail::fake();
    config(['monitoring.alert_email' => null]);

    $this->artisan('alerts:test')->expectsOutputToContain('ALERT_EMAIL')->assertFailed();

    Mail::assertNothingSent();
});

test('ينبّه إن كان ناقل البريد log فلن تصل الرسالة', function (): void {
    Mail::fake();
    config(['monitoring.alert_email' => 'ops@example.test', 'mail.default' => 'log']);

    $this->artisan('alerts:test')->expectsOutputToContain('MAIL_MAILER=log')->assertSuccessful();
});
