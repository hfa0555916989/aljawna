<?php

declare(strict_types=1);

use App\Models\Beneficiary;
use App\Support\SaudiIban;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * بيانات وهمية صالحة لنموذج المستفيد في اللوحة (لا بيانات حقيقية).
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function beneficiaryFormData(array $overrides = []): array
{
    return [
        'display_name' => 'سالم ماجد تركي العجاوني',
        'account_holder' => 'سالم ماجد تركي العجاوني',
        'bank_name' => 'مصرف الراجحي',
        'account_number' => '123456789012345',
        'iban' => SaudiIban::fromParts('80', '000000123456789012'),
        'target_amount' => '45000',
        'target_deadline' => today()->addMonths(2)->toDateString(),
        'recommended_deadline' => today()->addMonths(3)->toDateString(),
        'wedding_date' => today()->addMonths(4)->toDateString(),
        ...$overrides,
    ];
}

/**
 * قيم الحساب البنكي لمستفيد بكل صيغ عرضها، لإثبات غيابها عن الصفحات العامة.
 *
 * @return list<string>
 */
function bankSecretsOf(Beneficiary $beneficiary): array
{
    return [
        $beneficiary->iban,
        SaudiIban::grouped($beneficiary->iban),
        $beneficiary->account_number,
        $beneficiary->account_holder,
    ];
}

/**
 * صورة JPEG مولَّدة، واختياريًا بمقطع EXIF فيه اسم جهاز واتجاه (لإثبات إزالته عند الحفظ).
 */
function jpegBytes(int $width = 60, int $height = 40, ?string $exifMake = null, int $orientation = 1, int $red = 180): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, $red, 60, 60));
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();

    if ($exifMake === null) {
        return $jpeg;
    }

    $make = $exifMake."\0";
    $tiff = 'II'.pack('v', 42).pack('V', 8)
        .pack('v', 2)
        .pack('vvVV', 0x010F, 2, strlen($make), 8 + 2 + 2 * 12 + 4)
        .pack('vvV', 0x0112, 3, 1).pack('vv', $orientation, 0)
        .pack('V', 0)
        .$make;
    $payload = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);
}

/**
 * صورة PNG مولَّدة، واختياريًا بمقطع نصي tEXt مضمَّن (بيانات وصفية يجب ألا تبقى بعد الحفظ).
 */
function pngBytes(int $width = 60, int $height = 40, ?string $textChunk = null): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 90, 160));
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();

    if ($textChunk === null) {
        return $png;
    }

    $data = 'Comment'."\0".$textChunk;
    $chunk = pack('N', strlen($data)).'tEXt'.$data.pack('N', crc32('tEXt'.$data));

    return substr($png, 0, -12).$chunk.substr($png, -12);
}

function pdfBytes(string $marker = 'receipt'): string
{
    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Title ({$marker}) >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
}

/**
 * يثبت أن HTML المكوّن ولقطته وآثاره لا تحتوي أي قيمة من الحساب البنكي.
 */
function assertNoBankData(Testable $component, Beneficiary $beneficiary): void
{
    $payload = implode("\n", [
        $component->html(),
        json_encode($component->snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        json_encode($component->effects, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);

    foreach (bankSecretsOf($beneficiary) as $secret) {
        expect($payload)->not->toContain($secret);
    }
}
