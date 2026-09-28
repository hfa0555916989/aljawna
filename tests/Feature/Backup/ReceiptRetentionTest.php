<?php

declare(strict_types=1);

use App\Actions\Beneficiaries\ChangeBeneficiaryStatus;
use App\BeneficiaryStatus;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use App\Services\ReceiptBackup;
use App\Services\ReceiptRetention;
use App\Services\ReceiptStorage;
use App\Services\StatsService;
use App\Support\BackupCipher;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| حذف صور الإيصالات بعد إقفال المبادرة بستة أشهر (T21، docs/DECISIONS.md)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('receipts');
    Storage::fake('backups');
    config(['backup.encryption_key' => BackupCipher::generateKey()]);
});

/**
 * حوالة لإيصالها ملف في التخزين ونسخة في وجهة النسخ.
 */
function storedTransferFor(Beneficiary $beneficiary): Transfer
{
    $transfer = Transfer::factory()->for($beneficiary)->create(['amount' => '250.00']);
    Storage::disk('receipts')->put((string) $transfer->receipt_path, jpegBytes());
    Storage::disk('backups')->put(app(ReceiptBackup::class)->backupPath((string) $transfer->receipt_path), 'encrypted');

    return $transfer;
}

function assertReceiptKept(Transfer $transfer): void
{
    $fresh = $transfer->fresh();

    expect($fresh->receipt_path)->toBe($transfer->receipt_path)
        ->and($fresh->receipt_purged_at)->toBeNull()
        ->and(Storage::disk('receipts')->exists((string) $transfer->receipt_path))->toBeTrue()
        ->and(app(ReceiptBackup::class)->hasCopy((string) $transfer->receipt_path))->toBeTrue();
}

test('إيصالات مبادرة مقفلة منذ أكثر من 6 أشهر تُحذف من التخزين ومن النسخ، وتبقى الحوالة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->closed()->create(['closed_at' => now()->subMonths(6)->subDay()]);
    $transfer = storedTransferFor($beneficiary);
    $path = (string) $transfer->receipt_path;
    $statsBefore = app(StatsService::class)->forBeneficiary($beneficiary);

    $this->artisan('receipts:purge-expired')->assertSuccessful();

    $fresh = $transfer->fresh();

    expect(Storage::disk('receipts')->exists($path))->toBeFalse()
        ->and(app(ReceiptBackup::class)->hasCopy($path))->toBeFalse()
        ->and($fresh->receipt_path)->toBeNull()
        ->and($fresh->receipt_purged_at)->not->toBeNull()
        ->and($fresh->amount)->toBe('250.00')
        ->and($fresh->transferred_on->toDateString())->toBe($transfer->transferred_on->toDateString())
        ->and($fresh->user->full_name)->toBe($transfer->user->full_name)
        ->and($fresh->receipt_hash)->toBe($transfer->receipt_hash);

    StatsService::forget($beneficiary->id);
    expect(app(StatsService::class)->forBeneficiary($beneficiary))->toEqual($statsBefore);

    $audit = AuditLog::query()->where('action', ReceiptRetention::AUDIT_PURGED)->sole();
    expect($audit->subject_id)->toBe($beneficiary->id)
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->meta['purged'])->toBe(1)
        ->and($audit->meta['retention_months'])->toBe(6)
        ->and($audit->meta['issued_via'])->toBe('cli');
});

test('إيصالات المبادرات المفتوحة لا تُحذف أبدًا مهما قدمت', function (): void {
    $active = Beneficiary::factory()->approved()->create();
    $oldTransfer = storedTransferFor($active);
    $oldTransfer->forceFill(['created_at' => now()->subYears(3), 'transferred_on' => now()->subYears(3)])->save();

    // حتى لو بقي تاريخ إقفال قديم على مبادرة عادت متاحة بأي طريق، الحالة تحسم.
    $reopenedWithStaleDate = Beneficiary::factory()->approved()->create(['status' => BeneficiaryStatus::Active]);
    $reopenedWithStaleDate->forceFill(['closed_at' => now()->subYears(2)])->save();
    $staleTransfer = storedTransferFor($reopenedWithStaleDate);

    $unapproved = Beneficiary::factory()->create();
    $unapprovedTransfer = storedTransferFor($unapproved);

    $this->travel(5)->years();

    $this->artisan('receipts:purge-expired')->assertSuccessful();

    assertReceiptKept($oldTransfer);
    assertReceiptKept($staleTransfer);
    assertReceiptKept($unapprovedTransfer);
    expect(app(ReceiptRetention::class)->purge($reopenedWithStaleDate))->toBe(['purged' => 0, 'failed' => 0])
        ->and(AuditLog::query()->where('action', ReceiptRetention::AUDIT_PURGED)->exists())->toBeFalse();
});

test('المقفلة منذ أقل من 6 أشهر، أو بلا تاريخ إقفال، لا تُحذف إيصالاتها', function (): void {
    $recent = Beneficiary::factory()->approved()->closed()->create(['closed_at' => now()->subMonths(6)->addDay()]);
    $recentTransfer = storedTransferFor($recent);
    $undated = Beneficiary::factory()->approved()->closed()->create(['closed_at' => null]);
    $undatedTransfer = storedTransferFor($undated);

    $this->artisan('receipts:purge-expired')->assertSuccessful();

    assertReceiptKept($recentTransfer);
    assertReceiptKept($undatedTransfer);
});

test('--dry-run يعرض ما سيُحذف ولا يحذف شيئًا ولا يسجّل', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->closed()->create(['closed_at' => now()->subYear()]);
    $transfers = [storedTransferFor($beneficiary), storedTransferFor($beneficiary)];

    $this->artisan('receipts:purge-expired', ['--dry-run' => true])
        ->expectsOutputToContain('--dry-run')
        ->expectsTable(['المبادرة', 'تاريخ الإقفال', 'صور محفوظة', 'حُذفت'], [
            [$beneficiary->id, $beneficiary->closed_at->toDateString(), 2, '—'],
        ])
        ->assertSuccessful();

    foreach ($transfers as $transfer) {
        assertReceiptKept($transfer);
    }

    expect(AuditLog::query()->where('action', ReceiptRetention::AUDIT_PURGED)->exists())->toBeFalse();
});

test('إعادة فتح المبادرة تفرّغ تاريخ الإقفال فتتوقف مدة الاحتفاظ', function (): void {
    $this->seed(PermissionSeeder::class);
    $admin = User::factory()->admin()->create();
    $beneficiary = Beneficiary::factory()->approved()->create();
    $transfer = storedTransferFor($beneficiary);
    $close = app(ChangeBeneficiaryStatus::class);

    $close->handle($admin, $beneficiary, BeneficiaryStatus::Closed);
    expect($beneficiary->fresh()->closed_at)->not->toBeNull();

    $close->handle($admin, $beneficiary->fresh(), BeneficiaryStatus::Active);
    expect($beneficiary->fresh()->closed_at)->toBeNull();

    $this->travel(1)->year();
    $this->artisan('receipts:purge-expired')->assertSuccessful();

    assertReceiptKept($transfer);
});

test('فشل حذف النسخة الاحتياطية يُبقي الإيصال كما هو ليُعاد المحاولة', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->closed()->create(['closed_at' => now()->subYear()]);
    $transfer = storedTransferFor($beneficiary);

    $this->mock(ReceiptBackup::class, function ($mock): void {
        $mock->shouldReceive('deleteCopy')->andThrow(new RuntimeException('R2 unavailable'));
    });

    $this->artisan('receipts:purge-expired')->expectsOutputToContain('فشل حذف 1')->assertFailed();

    expect($transfer->fresh()->receipt_path)->toBe($transfer->receipt_path)
        ->and(Storage::disk('receipts')->exists((string) $transfer->receipt_path))->toBeTrue();
});

test('المبادر يرى أن صورة الإيصال حُذفت، ورابطها يعيد 404', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->closed()->create(['closed_at' => now()->subYear()]);
    $transfer = storedTransferFor($beneficiary);
    $this->artisan('receipts:purge-expired')->assertSuccessful();

    $this->actingAs($transfer->user)
        ->get(route('transfers.index'))
        ->assertOk()
        ->assertSee(__('transfers.index.receipt_purged'))
        ->assertDontSee(__('transfers.index.view_receipt'));

    $this->actingAs($transfer->user)
        ->get(app(ReceiptStorage::class)->temporaryUrl($transfer->fresh()))
        ->assertNotFound();
});

test('قيد القاعدة يمنع حوالة بلا إيصال ما لم تُحذف صورته بسياسة الاحتفاظ', function (): void {
    expect(fn () => Transfer::factory()->create(['receipt_path' => null]))->toThrow(QueryException::class);
});
