<?php

declare(strict_types=1);

use App\Mail\SystemAlert;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Services\ErrorCounter;
use App\Services\SystemAlerts;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
|--------------------------------------------------------------------------
| تنبيهات التشغيل بالبريد إلى ALERT_EMAIL (docs/DECISIONS.md)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    config(['monitoring.alert_email' => 'support@example.test']);
    Mail::fake();
});

/**
 * نظام يعمل: مرّ على أول فحص وقت كافٍ، والمجدول والطوابير ينبضان الآن.
 */
function healthySystem(): void
{
    test()->travel(-1)->hours();
    app(SystemAlerts::class)->check();
    test()->travelBack();

    SystemHeartbeat::beat(SystemHeartbeat::SCHEDULER);
    SystemHeartbeat::beat(SystemHeartbeat::QUEUE);
}

test('نظام سليم لا يرسل أي تنبيه', function (): void {
    healthySystem();

    expect(app(SystemAlerts::class)->check())->toBe([]);

    Mail::assertNothingSent();
});

test('توقف المجدول يرسل تنبيهًا واحدًا فقط (لا تنبيه منفصل عن الطوابير)', function (): void {
    healthySystem();

    $this->travel(10)->minutes();

    expect(app(SystemAlerts::class)->check())->toBe([SystemAlerts::SCHEDULER_STALLED]);

    Mail::assertSent(SystemAlert::class, fn (SystemAlert $mail): bool => $mail->hasTo('support@example.test')
        && $mail->alert === SystemAlerts::SCHEDULER_STALLED
        && str_contains($mail->envelope()->subject ?? '', 'المجدول متوقف'));
    Mail::assertSentCount(1);
});

test('توقف عامل الطوابير والمجدول يعمل يرسل تنبيه الطوابير', function (): void {
    healthySystem();

    $this->travel(10)->minutes();
    SystemHeartbeat::beat(SystemHeartbeat::SCHEDULER);

    expect(app(SystemAlerts::class)->check())->toBe([SystemAlerts::QUEUE_STALLED]);

    Mail::assertSent(SystemAlert::class, fn (SystemAlert $mail): bool => $mail->alert === SystemAlerts::QUEUE_STALLED);
});

test('نفس التنبيه لا يتكرر خلال مهلة التهدئة، ويعود بعدها ما دامت المشكلة قائمة', function (): void {
    config(['monitoring.alert_cooldown_minutes' => 60]);
    healthySystem();
    $this->travel(10)->minutes();

    app(SystemAlerts::class)->check();

    foreach (range(1, 5) as $ignored) {
        $this->travel(5)->minutes();
        expect(app(SystemAlerts::class)->check())->toBe([]);
    }

    Mail::assertSentCount(1);

    $this->travel(40)->minutes();

    expect(app(SystemAlerts::class)->check())->toBe([SystemAlerts::SCHEDULER_STALLED]);
    Mail::assertSentCount(2);
});

test('زوال المشكلة يعيد ضبط التهدئة فيُنبَّه فورًا إن عادت', function (): void {
    healthySystem();
    $this->travel(10)->minutes();
    app(SystemAlerts::class)->check();

    SystemHeartbeat::beat(SystemHeartbeat::SCHEDULER);
    SystemHeartbeat::beat(SystemHeartbeat::QUEUE);
    expect(app(SystemAlerts::class)->check())->toBe([]);

    $this->travel(10)->minutes();

    expect(app(SystemAlerts::class)->check())->toBe([SystemAlerts::SCHEDULER_STALLED]);
    Mail::assertSentCount(2);
});

test('لا تنبيه عن مكوّن لم ينبض قط في الدقائق الأولى بعد أول نشر، ثم يُنبَّه بعد المهلة', function (): void {
    expect(app(SystemAlerts::class)->check())->toBe([]);

    $this->travel(6)->minutes();

    expect(app(SystemAlerts::class)->check())->toBe([SystemAlerts::SCHEDULER_STALLED]);
});

test('فشل النسخ الاحتياطي المفعَّل يرسل تنبيهًا، وغير المُعدّ لا يرسل', function (): void {
    healthySystem();

    config(['monitoring.backup.enabled' => false]);
    expect(app(SystemAlerts::class)->check())->toBe([]);

    config(['monitoring.backup.enabled' => true]);
    SystemHeartbeat::recordReceiptsBackup(false);

    expect(app(SystemAlerts::class)->check())->toBe([SystemAlerts::BACKUP_FAILED]);
});

test('الارتفاع المفاجئ في الأخطاء يرسل تنبيهًا بعددها فقط، دون نص الخطأ', function (): void {
    config(['monitoring.error_spike.threshold' => 3]);
    healthySystem();

    foreach (range(1, 3) as $ignored) {
        report(new RuntimeException('فشل للجوال +966512345678'));
    }

    expect(app(SystemAlerts::class)->check())->toBe([SystemAlerts::ERROR_SPIKE]);

    Mail::assertSent(SystemAlert::class, function (SystemAlert $mail): bool {
        $body = $mail->render();

        return str_contains($body, '3 خطأ')
            && ! str_contains($body, '966512345678')
            && ! str_contains($body, 'RuntimeException');
    });
});

test('التنبيه لا يحتوي أي بيانات شخصية', function (): void {
    $user = User::factory()->admin()->create(['full_name' => 'سالم عبدالله محمد العجاوني', 'phone' => '+966512345678']);
    healthySystem();
    $this->travel(10)->minutes();

    app(SystemAlerts::class)->check();

    Mail::assertSent(SystemAlert::class, function (SystemAlert $mail) use ($user): bool {
        $body = $mail->render();

        return ! str_contains($body, $user->full_name)
            && ! str_contains($body, '512345678')
            // ولا مسار اللوحة غير المتوقع، فلا يمرّ عبر مزوّد البريد (T20).
            && ! str_contains($body, (string) config('admin.path'))
            && str_contains($body, 'صحة النظام');
    });
});

test('التنبيه يُرسل فورًا ولا يمرّ بالطوابير', function (): void {
    expect(new SystemAlert(SystemAlerts::QUEUE_STALLED, ''))
        ->not->toBeInstanceOf(ShouldQueue::class);
});

test('بلا ALERT_EMAIL لا يُرسل شيء ولا يُحجز التنبيه', function (): void {
    config(['monitoring.alert_email' => null]);
    healthySystem();
    $this->travel(10)->minutes();

    expect(app(SystemAlerts::class)->check())->toBe([]);
    Mail::assertNothingSent();

    config(['monitoring.alert_email' => 'support@example.test']);

    expect(app(SystemAlerts::class)->check())->toBe([SystemAlerts::SCHEDULER_STALLED]);
});

test('أمر monitor:check يفحص ويرسل', function (): void {
    healthySystem();
    $this->travel(10)->minutes();

    $this->artisan('monitor:check')->expectsOutputToContain(SystemAlerts::SCHEDULER_STALLED)->assertSuccessful();

    Mail::assertSentCount(1);
});

test('طلبات الويب تشغّل الفحص مرة كل فاصل على الأكثر فتكشف توقف المجدول نفسه', function (): void {
    healthySystem();
    $this->travel(10)->minutes();

    $this->get('/')->assertOk();
    Mail::assertSentCount(1);

    DB::table('system_alerts')->delete();

    $this->get('/')->assertOk();
    Mail::assertSentCount(1);

    $this->travel(6)->minutes();
    $this->get('/')->assertOk();

    Mail::assertSentCount(2);
});

test('عدّاد الأخطاء لا يُسقط الإبلاغ حين يتعطل التخزين المؤقت', function (): void {
    Cache::shouldReceive('add')->andThrow(new RuntimeException('cache down'));

    app(ErrorCounter::class)->record();

    expect(true)->toBeTrue();
});
