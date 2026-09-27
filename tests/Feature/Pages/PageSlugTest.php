<?php

declare(strict_types=1);

use App\Actions\Content\CreatePage;
use App\Actions\Content\SavePageDraft;
use App\Models\Page;
use App\Services\BaseDesign;
use App\Support\ReservedSlugs;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/*
|--------------------------------------------------------------------------
| مسارات الصفحات: لا تحجب صفحة مسارًا نظاميًا (T17 — FR-55)
|--------------------------------------------------------------------------
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake((string) config('security.branding.disk'));
});

function slugErrors(string $slug): array
{
    try {
        app(CreatePage::class)->handle(contentManager(), pageInput(['slug' => $slug]));
    } catch (ValidationException $exception) {
        return $exception->errors()['slug'] ?? [];
    }

    return [];
}

test('المسارات المحجوزة في ملف المهمة تُرفض برسالة عربية', function (string $slug): void {
    expect(slugErrors($slug))->toContain(__('pages.validation.slug_reserved'));
})->with([
    'admin', 'login', 'register', 'forgot-password', 'reset', 'beneficiaries',
    'dashboard', 'my-transfers', 'join', 'contact', 'up',
]);

test('كل مسار في قائمة المحجوزات بالإعدادات يُرفض', function (): void {
    foreach (config('security.pages.reserved_slugs') as $slug) {
        expect(slugErrors($slug))->toContain(__('pages.validation.slug_reserved'));
    }
});

test('أول مقطع من كل مسار مسجّل في النظام محجوز، ومنه اللوحة وlivewire', function (): void {
    $segments = ReservedSlugs::routeSegments();

    expect($segments)->toContain(config('admin.path'), 'up', 'logout', 'transfers', 'receipts', 'sitemap', 'brand')
        ->and(collect($segments)->contains(fn (string $segment): bool => str_starts_with($segment, 'livewire')))->toBeTrue();

    foreach ($segments as $segment) {
        if (ReservedSlugs::isValidFormat($segment)) {
            expect(slugErrors($segment))->toContain(__('pages.validation.slug_reserved'));
        }
    }
});

test('مسار لوحة الإدارة من ADMIN_PATH محجوز تلقائيًا، ولو تغيّر بعد تسجيل المسارات', function (): void {
    expect(slugErrors((string) config('admin.path')))->toContain(__('pages.validation.slug_reserved'));

    config(['admin.path' => 'my-panel-x1']);

    expect(ReservedSlugs::isReserved('my-panel-x1'))->toBeTrue()
        ->and(slugErrors('my-panel-x1'))->toContain(__('pages.validation.slug_reserved'));
});

test('مسار لوحة الإدارة الافتراضي ليس /admin المتوقَّع', function (): void {
    expect(config('admin.path'))->not->toBe('admin')
        ->and(ReservedSlugs::isValidFormat((string) config('admin.path')))->toBeTrue();
});

test('المسار المحجوز يُرفض ولو كُتب بحروف كبيرة أو بمسافات', function (string $slug): void {
    expect(slugErrors($slug))->not->toBeEmpty();

    expect(Page::query()->where('slug', 'admin')->exists())->toBeFalse();
})->with(['ADMIN', ' admin ', 'Login']);

test('صيغة المسار: لاتيني صغير وأرقام وشرطات مفردة فقط', function (string $slug): void {
    expect(slugErrors($slug))->toContain(__('pages.validation.slug_format'));
})->with([
    'عربي' => ['من-نحن'],
    'شرطة مائلة' => ['about/us'],
    'نقطة' => ['about.us'],
    'شرطة في البداية' => ['-about'],
    'شرطتان متتاليتان' => ['about--us'],
    'مسافة داخلية' => ['about us'],
    'شرطة سفلية' => ['about_us'],
]);

test('المسار الصالح يُقبل، والمسار المكرر يُرفض', function (): void {
    expect(slugErrors('about-us-2026'))->toBe([]);
    expect(slugErrors('about-us-2026'))->not->toBeEmpty();
});

test('صفحة منشورة بمسار نظامي أُدخلت مباشرة في القاعدة لا تحجب المسار النظامي', function (string $slug, string $routeName): void {
    Page::factory()->published()->create(['slug' => $slug, 'blocks' => [
        ['type' => 'hero', 'data' => ['title' => 'صفحة تحجب النظام', 'lead' => null, 'show_counters' => false, 'buttons' => []]],
    ]]);

    $this->get('/'.$slug)->assertOk()->assertDontSee('صفحة تحجب النظام');

    expect(app('router')->getRoutes()->match(request()->create('/'.$slug))->getName())->toBe($routeName);
})->with([
    'الدخول' => ['login', 'login'],
    'المبادرات' => ['beneficiaries', 'beneficiaries.index'],
    'خريطة الموقع' => ['sitemap.xml', 'sitemap'],
]);

test('GET لمسار نظامي بطريقة أخرى يبقى 405 ولا يلتقطه مسار الصفحات', function (): void {
    Page::factory()->published()->create(['slug' => 'logout']);

    $this->get('/logout')->assertStatus(405);
});

test('مسار الصفحة الرئيسية النظامية ثابت ولا يتغير بالحفظ', function (): void {
    $home = app(BaseDesign::class)->home();

    app(SavePageDraft::class)->handle(contentManager(), $home, pageInput(['slug' => 'new-home', 'blocks' => $home->blocks]));

    expect($home->refresh()->slug)->toBe(BaseDesign::HOME_SLUG);
    $this->get('/new-home')->assertNotFound();
    $this->get('/'.BaseDesign::HOME_SLUG)->assertNotFound();
});
