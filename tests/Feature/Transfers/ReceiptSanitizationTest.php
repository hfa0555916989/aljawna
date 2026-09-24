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
| إعادة ترميز صور الإيصالات وإزالة EXIF (T07 — §12.6)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    Storage::fake('receipts');
    $this->initiator = User::factory()->create();
    $this->beneficiary = Beneficiary::factory()->approved()->create();
});

function storeReceipt(User $user, Beneficiary $beneficiary, string $name, string $contents): string
{
    $transfer = app(CreateTransfer::class)->handle(
        $user, $beneficiary->id, '100', today()->toDateString(), null,
        UploadedFile::fake()->createWithContent($name, $contents),
    );

    return (string) Storage::disk('receipts')->get($transfer->receipt_path);
}

function exifOf(string $jpeg): array|false
{
    $stream = fopen('php://memory', 'w+b');
    fwrite($stream, $jpeg);
    rewind($stream);
    $exif = @exif_read_data($stream);
    fclose($stream);

    return $exif;
}

test('لا EXIF في صورة JPEG المحفوظة', function (): void {
    $original = jpegBytes(exifMake: 'SECRETCAM-GPS');

    expect(exifOf($original))->toHaveKey('Make', 'SECRETCAM-GPS');

    $stored = storeReceipt($this->initiator, $this->beneficiary, 'r.jpg', $original);

    $exif = exifOf($stored);

    expect($stored)->not->toContain('SECRETCAM-GPS')
        ->and($stored)->not->toContain("Exif\0\0")
        ->and($exif === false || ! isset($exif['Make']))->toBeTrue()
        ->and(getimagesizefromstring($stored)['mime'])->toBe('image/jpeg');
});

test('البصمة تُحسب للملف كما رُفع، والمحفوظ نسخة معاد ترميزها', function (): void {
    $original = jpegBytes(exifMake: 'CAM');

    storeReceipt($this->initiator, $this->beneficiary, 'r.jpg', $original);

    $transfer = Transfer::query()->sole();

    expect($transfer->receipt_hash)->toBe(hash('sha256', $original))
        ->and(Storage::disk('receipts')->get($transfer->receipt_path))->not->toBe($original);
});

test('اتجاه الصورة من EXIF يُطبَّق قبل إسقاطه', function (int $orientation, int $width, int $height): void {
    $stored = storeReceipt($this->initiator, $this->beneficiary, 'r.jpg', jpegBytes(40, 20, 'CAM', $orientation));

    [$storedWidth, $storedHeight] = getimagesizefromstring($stored);

    expect([$storedWidth, $storedHeight])->toBe([$width, $height]);
})->with([
    'بلا تدوير' => [1, 40, 20],
    'مقلوبة' => [3, 40, 20],
    'مائلة يمينًا' => [6, 20, 40],
    'مائلة يسارًا' => [8, 20, 40],
]);

test('صورة PNG يُعاد ترميزها وتسقط منها المقاطع النصية المضمَّنة', function (): void {
    $original = pngBytes(textChunk: 'SECRET-PNG-META');

    expect($original)->toContain('SECRET-PNG-META');

    $stored = storeReceipt($this->initiator, $this->beneficiary, 'r.png', $original);

    expect($stored)->not->toContain('SECRET-PNG-META')
        ->and($stored)->not->toContain('tEXt')
        ->and(getimagesizefromstring($stored)['mime'])->toBe('image/png');
});

test('اسم الملف المحفوظ عشوائي ولا يحمل اسم الملف الأصلي', function (): void {
    storeReceipt($this->initiator, $this->beneficiary, 'هوية-محمد-0555.jpg', jpegBytes());
    storeReceipt($this->initiator, $this->beneficiary, 'هوية-محمد-0555.jpg', jpegBytes(red: 20));

    $paths = Transfer::query()->pluck('receipt_path');

    expect($paths)->toHaveCount(2)
        ->and($paths[0])->not->toBe($paths[1]);

    foreach ($paths as $path) {
        expect($path)->toMatch('/^[A-Za-z0-9]{40}\.jpg$/')->not->toContain('0555');
    }
});
