<?php

declare(strict_types=1);

use App\Exceptions\StorageOperationFailed;
use App\Filament\Pages\Branding;
use App\HealthStatus;
use App\Livewire\Transfers\Create;
use App\Mail\SystemAlert;
use App\Models\Beneficiary;
use App\Models\SiteBranding;
use App\Models\SystemHeartbeat;
use App\Models\Transfer;
use App\Models\User;
use App\Services\BrandingImageStorage;
use App\Services\ReceiptStorage;
use App\Services\SystemAlerts;
use App\Services\SystemHealth;
use App\Support\BackupCipher;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\CloudBootstrapper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Config;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToWriteFile;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| أقراص Laravel Cloud (LARAVEL_CLOUD_DISK_CONFIG) وفشل التخزين الصريح (docs/RUNBOOK.md القسم 6)
|--------------------------------------------------------------------------
|
| على Laravel Cloud يستبدل CloudBootstrapper::configureDisks تعريف القرصين receipts وpublic
| كاملًا بتعريف s3 بـ throw: false، فيعيد فشل التخزين false بصمت. هذه الاختبارات تحاكي ذلك
| بالتعريف نفسه فوق قرص يفشل فعلًا، وتثبت أن الرفع والحذف والنسخ تفشل بوضوح.
|
*/

/**
 * يطبّق تعريف الأقراص كما يحقنه Laravel Cloud: receipts افتراضي وخاص، وpublic عام.
 */
function applyLaravelCloudDiskConfig(): void
{
    $_SERVER['LARAVEL_CLOUD_DISK_CONFIG'] = json_encode([
        [
            'disk' => 'receipts',
            'access_key_id' => 'test-receipts-key',
            'access_key_secret' => 'test-receipts-secret',
            'bucket' => 'aljawna-receipts',
            'url' => null,
            'endpoint' => 'https://account.r2.example.test',
            'is_default' => true,
        ],
        [
            'disk' => 'public',
            'access_key_id' => 'test-public-key',
            'access_key_secret' => 'test-public-secret',
            'bucket' => 'aljawna-public',
            'url' => 'https://public.example.test',
            'endpoint' => 'https://account.r2.example.test',
            'is_default' => false,
        ],
    ]);
    $_SERVER['FILESYSTEM_DISK'] = 'receipts';

    try {
        CloudBootstrapper::configureDisks(app());
    } finally {
        unset($_SERVER['LARAVEL_CLOUD_DISK_CONFIG'], $_SERVER['FILESYSTEM_DISK']);
    }
}

/**
 * قرص بتعريف معيّن (throw: false كما في Laravel Cloud) كل كتابة وقراءة وحذف عليه تفشل.
 *
 * @param  array<string, mixed>  $config
 */
function failingDisk(array $config): FilesystemAdapter
{
    $adapter = new class(storage_path('framework/testing/failing-disk')) extends LocalFilesystemAdapter
    {
        public function write(string $path, string $contents, Config $config): void
        {
            throw UnableToWriteFile::atLocation($path, 'simulated storage failure');
        }

        public function writeStream(string $path, $contents, Config $config): void
        {
            throw UnableToWriteFile::atLocation($path, 'simulated storage failure');
        }

        public function delete(string $path): void
        {
            throw UnableToDeleteFile::atLocation($path, 'simulated storage failure');
        }

        public function read(string $path): string
        {
            throw UnableToReadFile::fromLocation($path, 'simulated storage failure');
        }

        public function readStream(string $path)
        {
            throw UnableToReadFile::fromLocation($path, 'simulated storage failure');
        }
    };

    return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
}

/**
 * تعريف Laravel Cloud للقرص، ثم قرص يفشل فعلًا بالتعريف نفسه.
 */
function failCloudDisk(string $disk): void
{
    applyLaravelCloudDiskConfig();
    Storage::set($disk, failingDisk((array) config('filesystems.disks.'.$disk)));
}

beforeEach(function (): void {
    Storage::fake('backups');
    config(['backup.encryption_key' => BackupCipher::generateKey()]);
});

test('تعريف Laravel Cloud يستبدل القرصين كاملًا: s3 بلا root ولا visibility وthrow معطَّل، وreceipts افتراضي', function (): void {
    config(['filesystems.disks.receipts.root' => 'receipts', 'filesystems.disks.receipts.throw' => true]);

    applyLaravelCloudDiskConfig();

    $receipts = config('filesystems.disks.receipts');
    $public = config('filesystems.disks.public');

    expect($receipts['driver'])->toBe('s3')
        ->and($receipts['bucket'])->toBe('aljawna-receipts')
        ->and($receipts['throw'])->toBeFalse()
        ->and($receipts)->not->toHaveKey('root')
        ->and($receipts)->not->toHaveKey('visibility')
        ->and($public['driver'])->toBe('s3')
        ->and($public['url'])->toBe('https://public.example.test')
        ->and($public)->not->toHaveKey('visibility')
        ->and(config('filesystems.default'))->toBe('receipts')
        ->and(config('filesystems.disks.backups.driver'))->not->toBe('s3');

    // الرفع المؤقت يبقى على local رغم أن الافتراضي صار قرص s3.
    expect(config('livewire.temporary_file_upload.disk'))->toBe('local')
        ->and(FileUploadConfiguration::isUsingS3())->toBeFalse();
});

test('فشل حفظ الإيصال لا يُنشئ حوالة، ويرى المبادر رسالة خطأ لا رسالة نجاح', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-09-24 12:00', 'Asia/Riyadh'));
    $initiator = User::factory()->create();
    $beneficiary = Beneficiary::factory()->approved()->create();
    failCloudDisk('receipts');

    Livewire::actingAs($initiator)->test(Create::class)
        ->set('beneficiary_id', (string) $beneficiary->id)
        ->set('amount', '4500')
        ->set('transferred_on', '2026-09-23')
        ->set('bank_reference', '')
        ->set('receipt', UploadedFile::fake()->createWithContent('receipt.jpg', jpegBytes()))
        ->call('save')
        ->assertHasErrors(['receipt'])
        ->assertSee(__('transfers.validation.receipt_store_failed'))
        ->assertNoRedirect();

    expect(Transfer::query()->count())->toBe(0)
        ->and(session('status'))->toBeNull();
});

test('ReceiptStorage يرمي عند فشل الحذف بدل أن يمرّ صامتًا', function (): void {
    failCloudDisk('receipts');

    expect(fn () => app(ReceiptStorage::class)->delete('any.jpg'))->toThrow(StorageOperationFailed::class);
});

test('فشل حذف الإيصال الأصلي بعد 6 أشهر لا يمسح المسار، ويُسجَّل فشلًا ويطلق تنبيه البريد', function (): void {
    Mail::fake();
    config(['monitoring.alert_email' => 'ops@example.test']);

    $beneficiary = Beneficiary::factory()->approved()->closed()->create(['closed_at' => now()->subYear()]);
    $transfer = Transfer::factory()->for($beneficiary)->create();
    $path = (string) $transfer->receipt_path;
    failCloudDisk('receipts');

    $this->artisan('receipts:purge-expired')->expectsOutputToContain('فشل حذف 1')->assertFailed();

    expect($transfer->fresh()->receipt_path)->toBe($path)
        ->and($transfer->fresh()->receipt_purged_at)->toBeNull()
        ->and(SystemHeartbeat::named(SystemHeartbeat::RECEIPTS_PURGE)?->status)->toBe(SystemHeartbeat::BACKUP_FAILED)
        ->and(app(SystemHealth::class)->receiptsPurge()['status'])->toBe(HealthStatus::Failing);

    expect(app(SystemAlerts::class)->check())->toContain(SystemAlerts::RECEIPTS_PURGE_FAILED);

    Mail::assertSent(SystemAlert::class, fn (SystemAlert $mail): bool => $mail->alert === SystemAlerts::RECEIPTS_PURGE_FAILED
        && $mail->hasTo('ops@example.test'));
});

test('نجاح الحذف بعد الفشل يعيد البند سليمًا فيزول التنبيه', function (): void {
    SystemHeartbeat::recordReceiptsPurge(false);
    expect(app(SystemAlerts::class)->currentProblems())->toContain(SystemAlerts::RECEIPTS_PURGE_FAILED);

    $this->artisan('receipts:purge-expired')->assertSuccessful();

    expect(app(SystemHealth::class)->receiptsPurge()['status'])->toBe(HealthStatus::Ok)
        ->and(app(SystemAlerts::class)->currentProblems())->not->toContain(SystemAlerts::RECEIPTS_PURGE_FAILED);
});

test('--dry-run لا يسجّل نبضًا لحذف الإيصالات', function (): void {
    $this->artisan('receipts:purge-expired', ['--dry-run' => true])->assertSuccessful();

    expect(SystemHeartbeat::named(SystemHeartbeat::RECEIPTS_PURGE))->toBeNull();
});

test('فشل قراءة الإيصال من قرص Cloud يُفشل النسخ الاحتياطي بوضوح ويطلق التنبيه', function (): void {
    config(['monitoring.backup.enabled' => true]);
    Transfer::factory()->create();
    failCloudDisk('receipts');

    $this->artisan('backup:receipts')->expectsOutputToContain('فشل 1')->assertFailed();

    expect(Storage::disk('backups')->allFiles())->toBe([])
        ->and(SystemHeartbeat::named(SystemHeartbeat::RECEIPTS_BACKUP)?->status)->toBe(SystemHeartbeat::BACKUP_FAILED)
        ->and(app(SystemAlerts::class)->currentProblems())->toContain(SystemAlerts::BACKUP_FAILED);
});

test('BrandingImageStorage يرمي عند فشل الكتابة والحذف', function (): void {
    failCloudDisk('public');
    $storage = app(BrandingImageStorage::class);

    expect(fn () => $storage->store(UploadedFile::fake()->createWithContent('logo.png', pngBytes())))->toThrow(StorageOperationFailed::class)
        ->and(fn () => $storage->delete('old.png'))->toThrow(StorageOperationFailed::class);
});

test('فشل حفظ الشعار في صفحة الهوية: رسالة خطأ واضحة ولا يتغير شيء', function (): void {
    $this->seed(PermissionSeeder::class);
    $before = SiteBranding::current()->logo_light_path;
    failCloudDisk('public');

    Livewire::actingAs(contentManager())
        ->test(Branding::class)
        ->set('logoLight', UploadedFile::fake()->createWithContent('logo.png', pngBytes()))
        ->call('save')
        ->assertNotified(__('branding.store_failed'));

    expect(SiteBranding::current()->logo_light_path)->toBe($before);
});

test('فشل حذف الشعار القديم بعد حفظ الجديد: يُحفظ الجديد ويظهر تنبيه في اللوحة', function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake('public');

    $branding = SiteBranding::current();
    $branding->logo_light_path = 'old-logo.png';
    $branding->save();

    // الكتابة تنجح والحذف يفشل: قرص حقيقي مع حذف فاشل.
    $disk = Storage::disk('public');
    Storage::set('public', new class($disk) extends FilesystemAdapter
    {
        public function __construct(FilesystemAdapter $inner)
        {
            parent::__construct($inner->getDriver(), $inner->getAdapter(), $inner->getConfig());
        }

        public function delete($paths): bool
        {
            return false;
        }
    });

    Livewire::actingAs(contentManager())
        ->test(Branding::class)
        ->set('logoLight', UploadedFile::fake()->createWithContent('logo.png', pngBytes()))
        ->call('save')
        ->assertNotified(__('branding.delete_failed'));

    expect(SiteBranding::current()->logo_light_path)->not->toBe('old-logo.png');
});
