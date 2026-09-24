<?php

declare(strict_types=1);

use App\Actions\Transfers\CreateTransfer;
use App\Models\Beneficiary;
use App\Models\Transfer;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| كشف الحوالات المتكررة دون منعها (T07 — FR-17، §14 بند 4)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('receipts');
    $this->initiator = User::factory()->create();
    $this->beneficiary = Beneficiary::factory()->approved()->create(['target_amount' => '1000.00']);
});

/**
 * كل استدعاء بلا receipt يرفع صورة بأبعاد مختلفة، فلا تتطابق البصمات إلا عمدًا.
 *
 * @param  array{user?: User, beneficiary?: Beneficiary, amount?: string, date?: string, reference?: string|null, receipt?: string}  $overrides
 */
function uploadFor(object $test, array $overrides = []): Transfer
{
    static $uniqueWidth = 30;

    return app(CreateTransfer::class)->handle(
        $overrides['user'] ?? $test->initiator,
        ($overrides['beneficiary'] ?? $test->beneficiary)->id,
        $overrides['amount'] ?? '100',
        $overrides['date'] ?? '2026-09-20',
        $overrides['reference'] ?? null,
        UploadedFile::fake()->createWithContent('r.jpg', $overrides['receipt'] ?? jpegBytes(++$uniqueWidth, 20)),
    );
}

test('نفس بصمة الإيصال تُعلَّم متكررة ولو من مبادر آخر وبمبلغ مختلف', function (): void {
    $receipt = jpegBytes(33, 21);

    $first = uploadFor($this, ['receipt' => $receipt]);
    $second = uploadFor($this, ['receipt' => $receipt, 'user' => User::factory()->create(), 'amount' => '250']);

    expect($first->fresh()->is_repeated)->toBeFalse()
        ->and($second->is_repeated)->toBeTrue();
});

test('نفس رقم العملية يُعلَّم متكررًا ولو من مبادر آخر وبصيغة مختلفة', function (): void {
    $first = uploadFor($this, ['reference' => 'FT-2026-001']);
    $second = uploadFor($this, ['reference' => ' ft-2026-٠٠١ ', 'user' => User::factory()->create(), 'amount' => '999']);

    expect($first->receipt_hash)->not->toBe($second->receipt_hash)
        ->and($first->fresh()->is_repeated)->toBeFalse()
        ->and($second->is_repeated)->toBeTrue()
        ->and($second->bank_reference)->toBe('FT-2026-001');
});

test('نفس المبادر والمستفيد والمبلغ والتاريخ يُعلَّم متكررًا بإيصال مختلف وبلا رقم عملية', function (): void {
    $first = uploadFor($this, ['receipt' => jpegBytes(50, 20)]);
    $second = uploadFor($this, ['receipt' => jpegBytes(51, 20), 'amount' => '100.00']);

    expect($first->receipt_hash)->not->toBe($second->receipt_hash)
        ->and($first->fresh()->is_repeated)->toBeFalse()
        ->and($second->is_repeated)->toBeTrue();
});

test('اختلاف عنصر واحد من الرباعية بلا بصمة أو رقم عملية مشترك لا يُعدّ تكرارًا', function (string $field, mixed $value): void {
    $column = ['amount' => 'amount', 'date' => 'transferred_on', 'beneficiary' => 'beneficiary_id', 'user' => 'user_id'][$field];

    $first = uploadFor($this);
    $second = uploadFor($this, [$field => $value]);

    expect($first->receipt_hash)->not->toBe($second->receipt_hash)
        ->and($second->{$column})->not->toEqual($first->{$column})
        ->and($second->is_repeated)->toBeFalse();
})->with([
    'مبلغ مختلف' => fn (): array => ['amount', '101'],
    'تاريخ مختلف' => fn (): array => ['date', '2026-09-21'],
    'مستفيد مختلف' => fn (): array => ['beneficiary', Beneficiary::factory()->approved()->create()],
    'مبادر مختلف' => fn (): array => ['user', User::factory()->create()],
]);

test('غياب رقم العملية في حوالتين لا يجعلهما متكررتين', function (): void {
    uploadFor($this, ['amount' => '100']);

    expect(uploadFor($this, ['amount' => '200'])->is_repeated)->toBeFalse();
});

test('رقم عملية مختلف لا يُعدّ تكرارًا', function (): void {
    uploadFor($this, ['reference' => 'A1']);

    expect(uploadFor($this, ['reference' => 'A2', 'amount' => '200'])->is_repeated)->toBeFalse();
});

test('المتكررة لا تُمنع وتدخل الإحصائيات كغيرها', function (): void {
    $receipt = jpegBytes(40, 22);

    uploadFor($this, ['receipt' => $receipt, 'amount' => '100']);
    uploadFor($this, ['receipt' => $receipt, 'amount' => '100']);

    expect(Transfer::query()->count())->toBe(2)
        ->and(Transfer::query()->where('is_repeated', true)->count())->toBe(1)
        ->and($this->beneficiary->fresh()->collectedAmount())->toBe('200.00');
});
