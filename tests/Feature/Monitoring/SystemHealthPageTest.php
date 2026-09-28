<?php

declare(strict_types=1);

use App\Filament\Pages\SystemHealth;
use App\HealthStatus;
use App\Models\SystemHeartbeat;
use App\Models\User;
use App\Services\ErrorCounter;
use App\Services\SystemHealth as SystemHealthService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
|--------------------------------------------------------------------------
| صفحة "صحة النظام": للمدير فقط وللقراءة فقط (docs/DECISIONS.md)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
});

/**
 * @return array<string, HealthStatus>
 */
function healthStatuses(): array
{
    return collect(app(SystemHealthService::class)->checks())
        ->mapWithKeys(fn (array $check): array => [$check['key'] => $check['status']])
        ->all();
}

test('المدير يفتح الصفحة ويرى كل البنود والإصدارات', function (): void {
    $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath('system-health'))
        ->assertOk()
        ->assertSee('صحة النظام')
        ->assertSee('قاعدة البيانات')
        ->assertSee('Redis')
        ->assertSee('نبض عامل الطوابير')
        ->assertSee('آخر تشغيل للمجدول')
        ->assertSee('آخر نسخة احتياطية للإيصالات')
        ->assertSee('آخر نسخة احتياطية لقاعدة البيانات')
        ->assertSee('المهام الفاشلة')
        ->assertSee('الأخطاء خلال آخر 24 ساعة')
        ->assertSee('الإصدارات')
        ->assertSee(PHP_VERSION)
        ->assertSee(app()->version());
});

test('المشرف ولو ملك كل الصلاحيات لا يصل إلى الصفحة، والمبادر يحصل على 404', function (): void {
    $supervisor = User::factory()->supervisor()
        ->withPermissions(['security.view', 'settings.manage', 'stats.view'])
        ->create();

    $this->actingAs($supervisor)->get(adminPath('system-health'))->assertForbidden();
    $this->actingAs(User::factory()->create())->get(adminPath('system-health'))->assertNotFound();

    expect(SystemHealth::canAccess())->toBeFalse();
});

test('الزائر يُعاد إلى /login', function (): void {
    $this->get(adminPath('system-health'))->assertRedirect(route('login'));
});

test('الصفحة للقراءة فقط: لا أزرار ولا نماذج ولا إجراءات', function (): void {
    $html = $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath('system-health'))
        ->assertOk()
        ->getContent() ?: '';

    $main = (string) preg_replace('/.*<main[^>]*>(.*)<\/main>.*/s', '$1', $html);

    expect($main)->not->toContain('<form')
        ->and($main)->not->toContain('wire:click')
        ->and($main)->not->toContain('<button');
});

test('النسخ الاحتياطي "غير مُعدّ" ما لم يُفعَّل', function (): void {
    config(['monitoring.backup.enabled' => false]);

    expect(healthStatuses()['backup'])->toBe(HealthStatus::Unconfigured);

    $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath('system-health'))
        ->assertSee('غير مُعدّ');
});

test('قاعدة البيانات سليمة، والمجدول والطوابير "لم يُسجَّل بعد" قبل أول نبض', function (): void {
    $statuses = healthStatuses();

    expect($statuses['database'])->toBe(HealthStatus::Ok)
        ->and($statuses['scheduler'])->toBe(HealthStatus::Unknown)
        ->and($statuses['queue'])->toBe(HealthStatus::Unknown);
});

test('نبض المجدول والطوابير يُسجَّل من أمر monitor:heartbeat ويصبح قديمًا بعد مهلته', function (): void {
    $this->artisan('monitor:heartbeat')->assertSuccessful();

    expect(healthStatuses()['scheduler'])->toBe(HealthStatus::Ok)
        ->and(healthStatuses()['queue'])->toBe(HealthStatus::Ok);

    $this->travel(6)->minutes();

    expect(healthStatuses()['scheduler'])->toBe(HealthStatus::Failing)
        ->and(healthStatuses()['queue'])->toBe(HealthStatus::Failing);
});

test('المجدول يسجّل monitor:heartbeat كل دقيقة وmonitor:check كل 5 دقائق', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('monitor:heartbeat')
        ->expectsOutputToContain('monitor:check')
        ->assertSuccessful();
});

test('المهام الفاشلة تُعد من جدول failed_jobs', function (): void {
    expect(healthStatuses()['failed_jobs'])->toBe(HealthStatus::Ok);

    DB::table('failed_jobs')->insert([
        'uuid' => (string) Str::uuid(),
        'connection' => 'redis',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'Test',
        'failed_at' => now(),
    ]);

    $check = collect(app(SystemHealthService::class)->checks())->firstWhere('key', 'failed_jobs');

    expect($check['status'])->toBe(HealthStatus::Failing)
        ->and($check['value'])->toBe('1');
});

test('الأخطاء المُبلَّغ عنها تُعد خلال آخر 24 ساعة فقط', function (): void {
    report(new RuntimeException('خطأ تجريبي'));
    report(new RuntimeException('خطأ تجريبي آخر'));

    expect(app(ErrorCounter::class)->lastDay())->toBe(2);

    $this->travel(25)->hours();

    expect(app(ErrorCounter::class)->lastDay())->toBe(0);
});

test('النسخ الاحتياطي المفعَّل: نجاح حديث سليم، وفشل أو تأخر يحتاج انتباهًا', function (): void {
    config(['monitoring.backup.enabled' => true]);

    expect(healthStatuses()['backup'])->toBe(HealthStatus::Unknown);

    SystemHeartbeat::recordReceiptsBackup(true);
    expect(healthStatuses()['backup'])->toBe(HealthStatus::Ok);

    $this->travel(27)->hours();
    expect(healthStatuses()['backup'])->toBe(HealthStatus::Failing);

    SystemHeartbeat::recordReceiptsBackup(false);
    expect(healthStatuses()['backup'])->toBe(HealthStatus::Failing);
});

test('الصفحة لا تعرض أسرار الاتصال ولا تعتمد على مساحة القرص', function (): void {
    config(['database.connections.pgsql.password' => 'db-secret-value']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(adminPath('system-health'))
        ->assertOk()
        ->assertDontSee('db-secret-value')
        ->assertDontSee((string) config('database.connections.pgsql.host').':')
        ->assertDontSee('disk_free_space');

    expect(file_get_contents(app_path('Services/SystemHealth.php')))
        ->not->toContain('disk_free_space')
        ->not->toContain('disk_total_space');
});
