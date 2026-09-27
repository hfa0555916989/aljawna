<?php

declare(strict_types=1);

use App\Actions\Content\CreatePage;
use App\Actions\Content\PublishPage;
use App\Actions\Content\SavePageDraft;
use App\Models\Page;
use App\Support\PageBlocks;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| منشئ الصفحات: لا HTML حر ولا سكربت (T17 — docs/SPEC.md FR-53, §12.13)
|--------------------------------------------------------------------------
| النص المنسّق يُنظَّف على الخادم بقائمة سماح عند الحفظ، والنص العادي يُهرَّب
| عند العرض، والروابط غير المسموحة تُرفض برسالة عربية.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake((string) config('security.branding.disk'));
});

function publishedPageWithBlocks(array $blocks): Page
{
    $actor = contentManager();
    $page = app(CreatePage::class)->handle($actor, pageInput(['blocks' => $blocks]));
    app(PublishPage::class)->handle($actor, $page);

    return $page->refresh();
}

dataset('rich text xss payloads', [
    'وسم script' => ['<script>alert(1)</script>'],
    'img مع onerror' => ['<img src=x onerror=alert(1)>'],
    'svg مع onload' => ['<svg onload=alert(1)></svg>'],
    'رابط javascript' => ['<a href="javascript:alert(1)">اضغط</a>'],
    'رابط javascript مرمّز بكيانات' => ['<a href="jav&#x61;script:alert(1)">اضغط</a>'],
    'رابط javascript بحروف مختلطة ومسافات' => ['<a href=" JaVaScRiPt:alert(1)">اضغط</a>'],
    'رابط data' => ['<a href="data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==">اضغط</a>'],
    'iframe' => ['<iframe src="https://evil.test/alert(1)"></iframe>'],
    'سمة style وحدث onclick' => ['<p style="background:url(javascript:alert(1))" onclick="alert(1)">فقرة</p>'],
    'وسم style' => ['<style>body{background:url("https://evil.test/alert(1)")}</style>'],
    'form وbutton' => ['<form action="https://evil.test/alert(1)"><button>أرسل</button></form>'],
    'object وembed' => ['<object data="https://evil.test/alert(1)"></object><embed src="https://evil.test/alert(1)">'],
    'تداخل math وstyle' => ['<math><mtext><table><mglyph><style><img src=x onerror=alert(1)>'],
    'meta refresh' => ['<meta http-equiv="refresh" content="0;url=https://evil.test/alert(1)">'],
]);

test('حمولة XSS في النص المنسّق تُنظَّف عند الحفظ ولا تصل إلى الصفحة المنشورة', function (string $payload): void {
    $block = sampleBlocks()[PageBlocks::RICH_TEXT];
    $block['data']['body'] = '<p>نص آمن</p>'.$payload;

    $page = publishedPageWithBlocks([$block]);
    $storedBody = $page->blocks[0]['data']['body'];

    expect($storedBody)
        ->toContain('نص آمن')
        ->not->toContain('alert(1)')
        ->not->toContain('evil.test')
        ->not->toContain('<script')
        ->not->toContain('style=')
        ->not->toMatch('/\son\w+\s*=/i');

    $this->get(route('pages.show', $page->slug))
        ->assertOk()
        ->assertSee('نص آمن')
        ->assertDontSee('alert(1)', false)
        ->assertDontSee('evil.test', false);
})->with('rich text xss payloads');

test('النص المنسّق يحتفظ بالتنسيق المسموح ويفرض rel آمنة على الروابط', function (): void {
    $block = sampleBlocks()[PageBlocks::RICH_TEXT];
    $block['data']['body'] = '<h2 class="x">عنوان</h2><p><strong>عريض</strong> <em>مائل</em> <a href="https://example.com" target="_blank">رابط</a> <a href="tel:+966501234567">اتصال</a> <a href="/beneficiaries">داخلي</a></p><ul><li>بند</li></ul>';

    $page = publishedPageWithBlocks([$block]);

    expect($page->blocks[0]['data']['body'])
        ->toContain('<h2>عنوان</h2>')
        ->toContain('<strong>عريض</strong>')
        ->toContain('<em>مائل</em>')
        ->toContain('<a href="https://example.com" rel="nofollow noopener noreferrer">رابط</a>')
        ->toContain('<a href="tel:&#43;966501234567" rel="nofollow noopener noreferrer">اتصال</a>')
        ->toContain('<a href="/beneficiaries" rel="nofollow noopener noreferrer">داخلي</a>')
        ->toContain('<ul><li>بند</li></ul>')
        ->not->toContain('target=')
        ->not->toContain('class=');
});

test('النص العادي في كل الكتل يُعرض مهرَّبًا لا كوسوم', function (): void {
    $payload = '<script>alert(1)</script>';
    $blocks = sampleBlocks();
    $blocks[PageBlocks::HERO]['data']['title'] = 'عنوان '.$payload;
    $blocks[PageBlocks::CARDS]['data']['items'][0]['body'] = 'وصف '.$payload;
    $blocks[PageBlocks::FAQ]['data']['items'][0]['answer'] = 'جواب '.$payload;
    $blocks[PageBlocks::IMAGE]['data']['alt'] = 'بديل"><img src=x onerror=alert(1)>';

    $page = publishedPageWithBlocks(array_values($blocks));

    $this->get(route('pages.show', $page->slug))
        ->assertOk()
        ->assertDontSee($payload, false)
        ->assertDontSee('<img src=x onerror=alert(1)>', false)
        ->assertSee(e('عنوان '.$payload), false)
        ->assertSee(e('وصف '.$payload), false);
});

dataset('disallowed links', [
    'javascript' => ['javascript:alert(1)'],
    'javascript بحروف مختلطة' => ['JaVaScRiPt:alert(1)'],
    'javascript بمسافة بادئة ومحرف تحكّم' => ["java\tscript:alert(1)"],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'vbscript' => ['vbscript:msgbox(1)'],
    'mailto (لا بريد في النظام)' => ['mailto:someone@example.com'],
    'رابط يبدأ بـ //' => ['//evil.test/path'],
    'مسار بشرطة معكوسة' => ['/\\evil.test'],
    'رابط بعلامات اقتباس' => ['https://example.com/"><script>alert(1)</script>'],
    'ftp' => ['ftp://example.com/file'],
]);

test('الروابط غير المسموحة في كل حقول الروابط تُرفض برسالة عربية', function (string $url): void {
    $blocks = sampleBlocks();
    $hero = $blocks[PageBlocks::HERO];
    $hero['data']['buttons'][0]['url'] = $url;
    $cards = $blocks[PageBlocks::CARDS];
    $cards['data']['items'][0]['link_url'] = $url;

    try {
        app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [$hero, $cards]]));
        $this->fail('قُبل رابط غير مسموح.');
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('blocks.0.data.buttons.0.url')
            ->toHaveKey('blocks.1.data.items.0.link_url')
            ->and($exception->errors()['blocks.0.data.buttons.0.url'])->toContain(__('pages.validation.link'));
    }

    expect(Page::query()->where('slug', 'about-us')->exists())->toBeFalse();
})->with('disallowed links');

test('الروابط المسموحة تُقبل: https وhttp وwa.me وtel ومسار داخلي', function (string $url): void {
    $hero = sampleBlocks()[PageBlocks::HERO];
    $hero['data']['buttons'][0]['url'] = $url;

    $page = app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [$hero]]));

    expect($page->blocks[0]['data']['buttons'][0]['url'])->toBe($url);
})->with([
    'https' => ['https://example.com/about?x=1#top'],
    'http' => ['http://example.com'],
    'wa.me' => ['https://wa.me/966501234567'],
    'tel' => ['tel:+966501234567'],
    'مسار داخلي' => ['/beneficiaries'],
]);

test('نوع كتلة غير معروف (مثل HTML خام) يُرفض ولا يُحفظ', function (string $type): void {
    $page = Page::factory()->create();

    expect(fn () => app(SavePageDraft::class)->handle(contentManager(), $page, pageInput([
        'slug' => $page->slug,
        'blocks' => [['type' => $type, 'data' => ['html' => '<script>alert(1)</script>']]],
    ])))->toThrow(ValidationException::class, __('pages.validation.unknown_block'));

    expect($page->refresh()->blocks[0]['type'])->toBe(PageBlocks::HERO);
})->with(['html', 'iframe', 'script', 'css', 'embed']);

test('الحقول غير المعروفة داخل الكتلة (مثل style أو html) تُسقط عند الحفظ', function (): void {
    $hero = sampleBlocks()[PageBlocks::HERO];
    $hero['data']['style'] = 'background:red';
    $hero['data']['html'] = '<script>alert(1)</script>';

    $page = app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [$hero]]));

    expect($page->blocks[0]['data'])->not->toHaveKeys(['style', 'html']);
});

test('مسار صورة ليس ملفًا محفوظًا بالتطبيق يُرفض', function (string $path): void {
    $image = sampleBlocks()[PageBlocks::IMAGE];
    $image['data']['path'] = $path;

    expect(fn () => app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [$image]])))
        ->toThrow(ValidationException::class, __('pages.validation.image'));
})->with([
    'مسار للخلف' => ['../../.env'],
    'رابط خارجي' => ['https://evil.test/x.png'],
    'javascript' => ['javascript:alert(1)'],
    'اسم صحيح الصيغة غير موجود' => [str_repeat('b', 40).'.png'],
    'امتداد غير مسموح' => [str_repeat('a', 40).'.svg'],
]);
