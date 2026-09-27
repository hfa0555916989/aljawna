<?php

declare(strict_types=1);

use App\Actions\Content\CreatePage;
use App\Actions\Content\SaveMenus;
use App\Models\Page;
use App\Support\PageBlocks;
use App\Support\SaudiIban;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| لا آيبان ولا رقم حساب في محتوى الصفحات (T17 — FR-53, 30-security-privacy)
|--------------------------------------------------------------------------
| الحساب البنكي يُعرض من قاعدة المستفيدين فقط، فكل نص حر في كل كتلة (ومنه
| العناوين والروابط والنص المنسّق) وبيانات الصفحة والقوائم يُرفض إن احتواه.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake((string) config('security.branding.disk'));
});

/**
 * كل حقل نصي (text وrich وlink) في كل كتلة، ومنه حقول العناصر المتكررة.
 *
 * @return array<string, array{string, string, string}>
 */
function textFieldsOfAllBlocks(): array
{
    $cases = [];

    foreach (PageBlocks::schema() as $type => $fields) {
        foreach ($fields as $name => $spec) {
            if ($spec['kind'] === 'list') {
                foreach ($spec['fields'] as $itemName => $itemSpec) {
                    if (in_array($itemSpec['kind'], ['text', 'rich', 'link'], true)) {
                        $cases["{$type}.{$name}.{$itemName}"] = [$type, "{$name}.0.{$itemName}", $itemSpec['kind']];
                    }
                }
            } elseif (in_array($spec['kind'], ['text', 'rich', 'link'], true)) {
                $cases["{$type}.{$name}"] = [$type, $name, $spec['kind']];
            }
        }
    }

    return $cases;
}

function sampleIban(): string
{
    return SaudiIban::fromParts('80', '000000123456789012');
}

function textWith(string $kind, string $secret): string
{
    return match ($kind) {
        'link' => 'https://example.com/pay/'.$secret,
        'rich' => '<p>حوّل إلى '.$secret.'</p>',
        default => 'حوّل إلى '.$secret,
    };
}

function assertPageRejected(array $input, string $errorKey, string $messageKey): void
{
    try {
        app(CreatePage::class)->handle(contentManager(), $input);
        test()->fail("قُبل {$errorKey} وفيه بيانات حساب بنكي.");
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey($errorKey)
            ->and($exception->errors()[$errorKey])->toContain(__($messageKey));
    }

    expect(Page::query()->where('slug', $input['slug'])->exists())->toBeFalse();
}

test('آيبان في أي حقل نصي من أي كتلة يُرفض برسالة عربية', function (string $type, string $path, string $kind): void {
    $block = sampleBlocks()[$type];
    Arr::set($block['data'], $path, textWith($kind, sampleIban()));

    assertPageRejected(pageInput(['blocks' => [$block]]), "blocks.0.data.{$path}", 'pages.validation.iban_in_text');
})->with(textFieldsOfAllBlocks());

test('رقم حساب في أي حقل نصي من أي كتلة يُرفض برسالة عربية', function (string $type, string $path, string $kind): void {
    $block = sampleBlocks()[$type];
    Arr::set($block['data'], $path, textWith($kind, '123456789012345'));

    assertPageRejected(pageInput(['blocks' => [$block]]), "blocks.0.data.{$path}", 'pages.validation.account_in_text');
})->with(textFieldsOfAllBlocks());

test('الحالة الحدية: كل نوع كتلة فيه حقل نصي واحد على الأقل مشمول بالفحص', function (): void {
    $coveredTypes = array_unique(array_map(fn (array $case): string => $case[0], textFieldsOfAllBlocks()));

    expect(array_values(array_diff(PageBlocks::TYPES, $coveredTypes)))->toBe([PageBlocks::DIVIDER]);
});

test('الآيبان يُكشف بأي صيغة كُتب بها', function (string $written): void {
    $block = sampleBlocks()[PageBlocks::RICH_TEXT];
    $block['data']['body'] = '<p>الحساب: '.$written.'</p>';

    assertPageRejected(pageInput(['blocks' => [$block]]), 'blocks.0.data.body', 'pages.validation.iban_in_text');
})->with([
    'متصل' => fn (): string => sampleIban(),
    'مجموعات بمسافات' => fn (): string => SaudiIban::grouped(sampleIban()),
    'بحروف صغيرة' => fn (): string => strtolower(sampleIban()),
    'بأرقام عربية' => fn (): string => strtr(sampleIban(), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']),
    'بشرطات' => fn (): string => implode('-', str_split(sampleIban(), 4)),
    'مقطَّع بوسوم HTML' => fn (): string => '<strong>'.substr(sampleIban(), 0, 8).'</strong><em>'.substr(sampleIban(), 8).'</em>',
    'بكيانات HTML' => fn (): string => '&#83;&#65;'.substr(sampleIban(), 2),
    'داخل رابط href' => fn (): string => '<a href="https://example.com/'.sampleIban().'">ادفع</a>',
]);

test('رقم الحساب يُكشف ولو فُصل بمسافات أو شرطات أو كُتب بأرقام عربية', function (string $written): void {
    $block = sampleBlocks()[PageBlocks::HERO];
    $block['data']['lead'] = 'الحساب '.$written;

    assertPageRejected(pageInput(['blocks' => [$block]]), 'blocks.0.data.lead', 'pages.validation.account_in_text');
})->with([
    '10 أرقام متصلة' => ['1234567890'],
    'بمسافات' => ['1234 5678 9012 345'],
    'بشرطات' => ['123-456-789-012'],
    'بأرقام عربية' => ['١٢٣٤٥٦٧٨٩٠١٢'],
]);

test('أرقام الجوال السعودية والأرقام القصيرة مسموحة في المحتوى', function (string $text): void {
    $block = sampleBlocks()[PageBlocks::HERO];
    $block['data']['lead'] = $text;

    $page = app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [$block]]));

    expect($page->blocks[0]['data']['lead'])->toBe($text);
})->with([
    'جوال محلي' => ['للتواصل: 0501234567'],
    'جوال دولي بمسافات' => ['للتواصل: +966 50 123 4567'],
    'رقم من 9 أرقام' => ['رقم الطلب 123456789'],
    'مبلغ بفواصل' => ['المستهدف 1,500,000 ريال'],
    'تاريخ' => ['موعد الزواج 2026-10-15'],
]);

test('الآيبان ورقم الحساب يُرفضان في عنوان الصفحة ووصفها ومسارها أيضًا', function (string $field, string $value, string $messageKey): void {
    assertPageRejected(pageInput([$field => $value]), $field, $messageKey);
})->with([
    'العنوان مع آيبان' => fn (): array => ['title', 'ادعم '.sampleIban(), 'pages.validation.iban_in_text'],
    'الوصف مع رقم حساب' => ['seo_description', 'الحساب 123456789012345', 'pages.validation.account_in_text'],
    'المسار مع آيبان' => fn (): array => ['slug', strtolower(sampleIban()), 'pages.validation.iban_in_text'],
]);

test('الآيبان ورقم الحساب يُرفضان في نص عناصر القوائم وروابطها', function (array $item, string $errorKey, string $messageKey): void {
    try {
        app(SaveMenus::class)->handle(contentManager(), ['header' => [$item], 'footer' => []]);
        $this->fail('قُبل عنصر قائمة فيه بيانات حساب بنكي.');
    } catch (ValidationException $exception) {
        expect($exception->errors()[$errorKey] ?? [])->toContain(__($messageKey));
    }
})->with([
    'نص مع آيبان' => fn (): array => [['label' => sampleIban(), 'url' => '/beneficiaries'], 'header.0.label', 'pages.validation.iban_in_text'],
    'رابط مع رقم حساب' => [['label' => 'ادعم', 'url' => 'https://example.com/123456789012345'], 'header.0.url', 'pages.validation.account_in_text'],
]);
