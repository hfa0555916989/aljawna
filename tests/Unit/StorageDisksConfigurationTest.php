<?php

declare(strict_types=1);

use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

/*
|--------------------------------------------------------------------------
| أقراص التخزين على Laravel Cloud (docs/RUNBOOK.md القسم 6)
|--------------------------------------------------------------------------
|
| R2 يرفض visibility لكل كائن، والرفع المؤقت في Livewire يبقى على الخادم مهما كان
| FILESYSTEM_DISK، فلا ينتقل إلى الرفع المباشر من المتصفح الذي تمنعه CSP.
|
*/

/**
 * يقيّم config/filesystems.php بمتغيرات بيئة مؤقتة ثم يعيدها كما كانت.
 *
 * @param  array<string, string>  $variables
 * @return array<string, mixed>
 */
function filesystemsConfigWith(array $variables): array
{
    $previous = [];

    foreach ($variables as $name => $value) {
        $previous[$name] = [$_SERVER[$name] ?? null, $_ENV[$name] ?? null];
        $_SERVER[$name] = $_ENV[$name] = $value;
    }

    try {
        return require config_path('filesystems.php');
    } finally {
        foreach ($previous as $name => [$server, $env]) {
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }

            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
        }
    }
}

test('قرصا الإيصالات والصور العامة بلا visibility عندما يكون الـ driver هو s3', function (): void {
    $disks = filesystemsConfigWith([
        'RECEIPTS_FILESYSTEM_DRIVER' => 's3',
        'PUBLIC_FILESYSTEM_DRIVER' => 's3',
    ])['disks'];

    expect($disks['receipts']['driver'])->toBe('s3')
        ->and($disks['receipts'])->not->toHaveKey('visibility')
        ->and($disks['public']['driver'])->toBe('s3')
        ->and($disks['public'])->not->toHaveKey('visibility');
});

test('القرصان المحليان يبقيان بـ visibility كما هي', function (): void {
    $disks = filesystemsConfigWith([
        'RECEIPTS_FILESYSTEM_DRIVER' => 'local',
        'PUBLIC_FILESYSTEM_DRIVER' => 'local',
    ])['disks'];

    expect($disks['receipts']['driver'])->toBe('local')
        ->and($disks['receipts']['visibility'])->toBe('private')
        ->and($disks['public']['driver'])->toBe('local')
        ->and($disks['public']['visibility'])->toBe('public');
});

test('قرص النسخ الاحتياطي بلا visibility ولا يعود إلى AWS_*', function (): void {
    $backups = filesystemsConfigWith([
        'BACKUP_FILESYSTEM_DRIVER' => 's3',
        'AWS_ACCESS_KEY_ID' => 'injected-by-cloud',
        'AWS_BUCKET' => 'injected-bucket',
    ])['disks']['backups'];

    expect($backups['driver'])->toBe('s3')
        ->and($backups)->not->toHaveKey('visibility')
        ->and($backups['key'])->not->toBe('injected-by-cloud')
        ->and($backups['bucket'])->not->toBe('injected-bucket');
});

test('الرفع المؤقت في Livewire على القرص local صراحة', function (): void {
    expect(config('livewire.temporary_file_upload.disk'))->toBe('local')
        ->and(config('livewire.temporary_file_upload.directory'))->toBeNull()
        ->and(config('livewire.temporary_file_upload.max_upload_time'))->toBe(5);
});

test('Livewire لا ينتقل إلى الرفع المباشر إلى S3 ولو صار FILESYSTEM_DISK قرص s3', function (): void {
    config([
        'filesystems.default' => 'receipts',
        'filesystems.disks.receipts.driver' => 's3',
    ]);

    expect(FileUploadConfiguration::isUsingS3())->toBeFalse();

    config(['livewire.temporary_file_upload.disk' => null]);

    // بدون الإعداد الصريح كان سيتحول إلى S3: هذا ما يمنعه config/livewire.php.
    expect(FileUploadConfiguration::isUsingS3())->toBeTrue();
});

test('FILESYSTEM_DISK=receipts (bucket الإيصالات هو Default disk) لا يغيّر قرص الرفع المؤقت', function (): void {
    config(['filesystems' => filesystemsConfigWith([
        'FILESYSTEM_DISK' => 'receipts',
        'RECEIPTS_FILESYSTEM_DRIVER' => 's3',
    ])]);

    expect(config('filesystems.default'))->toBe('receipts')
        ->and(config('filesystems.disks.receipts.driver'))->toBe('s3')
        ->and(config('livewire.temporary_file_upload.disk'))->toBe('local')
        ->and(FileUploadConfiguration::isUsingS3())->toBeFalse();
});

test('إعدادات Livewire الأخرى لم تتأثر بملف config/livewire.php الجزئي', function (): void {
    expect(config('livewire.class_namespace'))->not->toBeNull()
        ->and(config('livewire.temporary_file_upload.preview_mimes'))->toContain('png', 'jpg');
});
