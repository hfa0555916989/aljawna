<?php

declare(strict_types=1);

use App\HealthStatus;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\SystemHeartbeat;
use App\Models\Transfer;
use App\Models\User;
use App\Services\Audit;
use App\Services\DatabaseBackup;
use App\Services\SystemAlerts;
use App\Services\SystemHealth;
use App\Support\BackupCipher;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| نسخة قاعدة البيانات اليومية المشفّرة واسترجاعها (T21، docs/RUNBOOK.md)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('backups');
    config([
        'backup.encryption_key' => BackupCipher::generateKey(),
        'monitoring.database_backup.enabled' => true,
    ]);
    $this->seed(PermissionSeeder::class);
});

/**
 * كل صفوف الجداول المهمة بترتيب ثابت، لمقارنة ما قبل النسخ بما بعد الاسترجاع.
 *
 * @return array<string, list<array<string, mixed>>>
 */
function databaseSnapshot(): array
{
    $snapshot = [];

    foreach (['users', 'beneficiaries', 'transfers', 'audit_logs', 'permissions', 'model_has_permissions'] as $table) {
        $orderColumn = $table === 'model_has_permissions' ? 'permission_id' : 'id';
        $snapshot[$table] = DB::table($table)->orderBy($orderColumn)->get()->map(fn (object $row): array => (array) $row)->all();
    }

    return $snapshot;
}

function seedBackupData(): void
{
    $admin = User::factory()->admin()->create();
    User::factory()->supervisor()->withPermissions(['transfers.view'])->create();
    $open = Beneficiary::factory()->approved()->create();
    $closed = Beneficiary::factory()->approved()->closed()->create();
    Transfer::factory()->count(3)->for($open)->create();
    Transfer::factory()->repeated()->for($closed)->create(['bank_reference' => 'REF-1']);
    Audit::record('test.backup', $open, ['note' => 'قيمة عربية', 'nested' => ['a' => 1]], $admin);
}

test('النسخة تُسترجع فعلًا: البيانات كما كانت، والتسلسلات مضبوطة، وقيود الإضافة فقط باقية', function (): void {
    seedBackupData();
    $before = databaseSnapshot();

    $this->artisan('backup:database')->assertSuccessful();

    $path = app(DatabaseBackup::class)->latest();
    expect($path)->not->toBeNull();

    // تغيير البيانات بعد النسخ: يجب أن يختفي كله بالاسترجاع.
    User::factory()->count(2)->create();
    Transfer::query()->delete();

    $this->artisan('backup:restore-database', ['--latest' => true, '--wipe' => true, '--force' => true])
        ->assertSuccessful();

    expect(databaseSnapshot())->toEqual($before);

    $newUser = User::factory()->create();
    expect($newUser->id)->toBeGreaterThan(collect($before['users'])->max('id'));

    expect(fn () => AuditLog::query()->delete())->toThrow(Exception::class);
});

test('النسخة مشفّرة ولا تحتوي نصًا مقروءًا من البيانات', function (): void {
    $user = User::factory()->create(['full_name' => 'مبادر اسمه مميز جدا للاختبار']);

    $path = app(DatabaseBackup::class)->create();
    $contents = (string) Storage::disk('backups')->get($path);

    expect($contents)->toStartWith(BackupCipher::MAGIC)
        ->and($contents)->not->toContain($user->phone)
        ->and($contents)->not->toContain('مميز');
});

test('--verify يتحقق من النسخة دون أن يكتب شيئًا', function (): void {
    seedBackupData();
    app(DatabaseBackup::class)->create();
    $before = databaseSnapshot();

    $this->artisan('backup:restore-database', ['--latest' => true, '--verify' => true])
        ->expectsOutputToContain('النسخة سليمة')
        ->assertSuccessful();

    expect(databaseSnapshot())->toEqual($before);
});

test('النسخة التالفة تُرفض ولا تُمسّ القاعدة', function (): void {
    seedBackupData();
    $path = app(DatabaseBackup::class)->create();
    $contents = (string) Storage::disk('backups')->get($path);
    Storage::disk('backups')->put($path, substr_replace($contents, chr(ord($contents[80]) ^ 1), 80, 1));
    $before = databaseSnapshot();

    $this->artisan('backup:restore-database', ['backup' => $path, '--wipe' => true, '--force' => true])
        ->expectsOutputToContain('فشل')
        ->assertFailed();

    expect(databaseSnapshot())->toEqual($before);
});

test('الاسترجاع بمفتاح آخر يُرفض', function (): void {
    $path = app(DatabaseBackup::class)->create();
    config(['backup.encryption_key' => BackupCipher::generateKey()]);

    $this->artisan('backup:restore-database', ['backup' => $path, '--verify' => true])
        ->expectsOutputToContain('بمفتاح غير')
        ->assertFailed();
});

test('لا استرجاع فوق قاعدة غير فارغة دون --wipe', function (): void {
    seedBackupData();
    $path = app(DatabaseBackup::class)->create();
    $before = databaseSnapshot();

    $this->artisan('backup:restore-database', ['backup' => $path, '--force' => true])
        ->expectsOutputToContain('ليست فارغة')
        ->assertFailed();

    expect(databaseSnapshot())->toEqual($before);
});

test('الاسترجاع يطلب تأكيدًا ما لم يُمرَّر --force', function (): void {
    app(DatabaseBackup::class)->create();

    $this->artisan('backup:restore-database', ['--latest' => true, '--wipe' => true])
        ->expectsConfirmation('سيُحذف كل ما في قاعدة البيانات الحالية ('.config('database.connections.pgsql.database').') ويُستبدل بالنسخة '.app(DatabaseBackup::class)->latest().'. متابعة؟', 'no')
        ->assertFailed();
});

test('سياسة الاحتفاظ تُطبَّق بعد كل نسخة ناجحة', function (): void {
    $now = CarbonImmutable::parse('2026-09-28 00:00', 'UTC');
    $this->travelTo($now);

    foreach ([1, 2, 20, 40, 100, 200, 400] as $daysAgo) {
        Storage::disk('backups')->put('database/'.$now->subDays($daysAgo)->format('Y-m-d\THis\Z').'.ajdb', 'x');
    }

    $this->artisan('backup:database')->assertSuccessful();

    $remaining = collect(Storage::disk('backups')->files('database'))->sort()->values();

    expect($remaining)->toContain('database/'.$now->subDays(1)->format('Y-m-d\THis\Z').'.ajdb')
        ->and($remaining)->toContain('database/'.$now->subDays(40)->format('Y-m-d\THis\Z').'.ajdb')
        ->and($remaining)->toContain('database/'.$now->subDays(100)->format('Y-m-d\THis\Z').'.ajdb')
        ->and($remaining)->not->toContain('database/'.$now->subDays(200)->format('Y-m-d\THis\Z').'.ajdb')
        ->and($remaining)->not->toContain('database/'.$now->subDays(400)->format('Y-m-d\THis\Z').'.ajdb');
});

test('النجاح والفشل يُسجَّلان في صحة النظام، والفشل يطلق تنبيه البريد', function (): void {
    config(['monitoring.alert_email' => 'ops@example.test', 'monitoring.backup.enabled' => false]);
    Mail::fake();

    $this->artisan('backup:database')->assertSuccessful();
    expect(app(SystemHealth::class)->databaseBackup()['status'])->toBe(HealthStatus::Ok);

    config(['backup.encryption_key' => null]);
    $this->artisan('backup:database')->assertFailed();

    expect(SystemHeartbeat::named(SystemHeartbeat::DATABASE_BACKUP)?->status)->toBe(SystemHeartbeat::BACKUP_FAILED)
        ->and(app(SystemHealth::class)->databaseBackup()['status'])->toBe(HealthStatus::Failing)
        ->and(app(SystemAlerts::class)->currentProblems())->toContain(SystemAlerts::DATABASE_BACKUP_FAILED);
});

test('التأخر أكثر من المهلة يُعد فشلًا', function (): void {
    SystemHeartbeat::recordDatabaseBackup(true);
    expect(app(SystemHealth::class)->databaseBackup()['status'])->toBe(HealthStatus::Ok);

    $this->travel(27)->hours();

    expect(app(SystemHealth::class)->databaseBackup()['status'])->toBe(HealthStatus::Failing);
});

test('غير المفعَّل لا ينسخ ولا يسجّل نبضًا، ويظهر "غير مُعدّ"', function (): void {
    config(['monitoring.database_backup.enabled' => false]);

    $this->artisan('backup:database')->assertSuccessful();

    expect(Storage::disk('backups')->files('database'))->toBe([])
        ->and(SystemHeartbeat::named(SystemHeartbeat::DATABASE_BACKUP))->toBeNull()
        ->and(app(SystemHealth::class)->databaseBackup()['status'])->toBe(HealthStatus::Unconfigured);
});

test('backup:list يعرض النسخ', function (): void {
    $path = app(DatabaseBackup::class)->create();

    $this->artisan('backup:list')->expectsOutputToContain($path)->assertSuccessful();
});
