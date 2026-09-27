<?php

declare(strict_types=1);

use App\Actions\Content\CreatePage;
use App\Actions\Content\PublishPage;
use App\Actions\Content\RestoreMenuRevision;
use App\Actions\Content\RestorePageRevision;
use App\Actions\Content\SaveMenus;
use App\Actions\Content\SavePageDraft;
use App\Actions\Content\UnpublishPage;
use App\Filament\Pages\Menus;
use App\Filament\Resources\Pages\Pages\CreatePage as CreatePageScreen;
use App\MenuLocation;
use App\Models\AuditLog;
use App\Models\MenuRevision;
use App\Models\Page;
use App\Models\User;
use App\PageStatus;
use App\Services\BaseDesign;
use App\Services\SiteMenu;
use App\Support\PageBlocks;
use App\Support\SaudiIban;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| صلاحيات منشئ الصفحات وحدوده والقوائم وخريطة الموقع (T17 — FR-50..56, §12.13)
|--------------------------------------------------------------------------
| content.manage فحص على الخادم لكل شاشة وكل إجراء، والحدود تُفرض في الإجراءات.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake((string) config('security.branding.disk'));
});

/**
 * @return array<string, Closure(): User>
 */
function usersWithoutContentManage(): array
{
    return [
        'مشرف بلا الصلاحية' => fn (): User => User::factory()->supervisor()->withPermissions(['beneficiaries.manage', 'settings.manage'])->create(),
        'مبادر' => fn (): User => User::factory()->create(),
        'مدير معطّل' => fn (): User => User::factory()->admin()->inactive()->create(),
    ];
}

/**
 * @return array<string, mixed>
 */
function validationErrorsFor(Closure $attempt): array
{
    try {
        $attempt();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    throw new RuntimeException('Expected a validation exception.');
}

test('شاشات المنشئ والقوائم والمعاينة 403 لمشرف بلا content.manage', function (string $uri): void {
    $page = Page::factory()->create();
    $supervisor = User::factory()->supervisor()->withPermissions(['beneficiaries.manage'])->create();

    $this->actingAs($supervisor)
        ->get(str_replace('{page}', (string) $page->id, $uri))
        ->assertForbidden();
})->with([
    'قائمة الصفحات' => ['/admin/content/pages'],
    'إنشاء صفحة' => ['/admin/content/pages/create'],
    'تحرير صفحة' => ['/admin/content/pages/{page}/edit'],
    'المعاينة' => ['/admin/content/pages/{page}/preview'],
    'القوائم' => ['/admin/content/menus'],
]);

test('المعاينة تحوّل الزائر إلى الدخول ولا تعرض المسودة', function (): void {
    $page = Page::factory()->create(['blocks' => [sampleBlocks()[PageBlocks::HERO]]]);

    $this->get(route('admin.pages.preview', $page))
        ->assertRedirect()
        ->assertDontSee('عنوان الصفحة');
});

test('من يملك content.manage يفتح شاشات المنشئ والقوائم، والمدير ضمنيًا', function (User $actor): void {
    $page = Page::factory()->create();

    $this->actingAs($actor);

    foreach (['/admin/content/pages', '/admin/content/pages/create', "/admin/content/pages/{$page->id}/edit", '/admin/content/menus'] as $uri) {
        $this->get($uri)->assertOk();
    }

    $this->get(route('admin.pages.preview', $page))->assertOk();
})->with([
    'مشرف بالصلاحية' => fn (): User => contentManager(),
    'مدير' => fn (): User => User::factory()->admin()->create(),
]);

test('كل إجراءات المنشئ ترفض من لا يملك content.manage ولا تكتب شيئًا', function (User $actor): void {
    $manager = contentManager();
    $page = app(CreatePage::class)->handle($manager, pageInput());
    $revision = app(PublishPage::class)->handle($manager, $page);
    $menuRevision = app(SaveMenus::class)->handle($manager, ['header' => [['label' => 'رابط', 'url' => '/beneficiaries']], 'footer' => []]);
    $auditCount = AuditLog::query()->count();

    $attempts = [
        fn () => app(CreatePage::class)->handle($actor, pageInput(['slug' => 'another-page'])),
        fn () => app(SavePageDraft::class)->handle($actor, $page, pageInput(['title' => 'عنوان مختلف'])),
        fn () => app(PublishPage::class)->handle($actor, $page),
        fn () => app(UnpublishPage::class)->handle($actor, $page),
        fn () => app(RestorePageRevision::class)->handle($actor, $page, $revision),
        fn () => app(SaveMenus::class)->handle($actor, ['header' => [], 'footer' => []]),
        fn () => app(RestoreMenuRevision::class)->handle($actor, $menuRevision),
    ];

    foreach ($attempts as $attempt) {
        expect($attempt)->toThrow(AuthorizationException::class);
    }

    expect(Page::query()->count())->toBe(1)
        ->and($page->refresh()->title)->toBe('من نحن')
        ->and($page->status)->toBe(PageStatus::Published)
        ->and($page->revisions()->count())->toBe(1)
        ->and(MenuRevision::query()->count())->toBe(1)
        ->and(AuditLog::query()->count())->toBe($auditCount);
})->with(usersWithoutContentManage());

test('حد عدد الكتل في الصفحة يُفرض برسالة عربية', function (): void {
    config(['security.pages.max_blocks' => 2]);
    $divider = sampleBlocks()[PageBlocks::DIVIDER];

    $errors = validationErrorsFor(fn () => app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [$divider, $divider, $divider]])));

    expect($errors['blocks'])->toBe([__('pages.validation.too_many_blocks', ['max' => 2])])
        ->and(Page::query()->count())->toBe(0);

    app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [$divider, $divider]]));
    expect(Page::query()->count())->toBe(1);
});

test('حد حجم الصفحة يُفرض برسالة عربية', function (): void {
    config(['security.pages.max_kilobytes' => 1]);
    $text = sampleBlocks()[PageBlocks::RICH_TEXT];
    $text['data']['body'] = '<p>'.str_repeat('نص طويل ', 200).'</p>';

    $errors = validationErrorsFor(fn () => app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [$text]])));

    expect($errors['blocks'])->toBe([__('pages.validation.too_large', ['max' => 1])])
        ->and(Page::query()->count())->toBe(0);
});

test('حد عدد الصفحات يشمل الرئيسية ويُفرض برسالة عربية', function (): void {
    config(['security.pages.max_pages' => 2]);
    app(BaseDesign::class)->home();
    app(CreatePage::class)->handle(contentManager(), pageInput());

    $errors = validationErrorsFor(fn () => app(CreatePage::class)->handle(contentManager(), pageInput(['slug' => 'third-page'])));

    expect($errors['title'])->toBe([__('pages.validation.too_many_pages', ['max' => 2])])
        ->and(Page::query()->count())->toBe(2);
});

test('حد عناصر كل قائمة يُفرض، ولا تُكتب نسخة عند الرفض', function (): void {
    config(['security.pages.max_menu_items' => 2]);
    $item = ['label' => 'رابط', 'url' => '/beneficiaries'];

    $errors = validationErrorsFor(fn () => app(SaveMenus::class)->handle(contentManager(), ['header' => [$item, $item, $item], 'footer' => []]));

    expect($errors['header'])->toBe([__('pages.validation.list_max', ['max' => 2])])
        ->and(MenuRevision::query()->count())->toBe(0);
});

test('روابط القوائم غير المسموحة تُرفض', function (string $url): void {
    $errors = validationErrorsFor(fn () => app(SaveMenus::class)->handle(contentManager(), [
        'header' => [['label' => 'رابط', 'url' => $url]],
        'footer' => [],
    ]));

    expect($errors['header.0.url'])->toBe([__('pages.validation.link')])
        ->and(MenuRevision::query()->count())->toBe(0);
})->with([
    'javascript' => ['javascript:alert(1)'],
    'data' => ['data:text/html,<script>alert(1)</script>'],
    'mailto' => ['mailto:someone@example.com'],
    'نطاق بلا مخطط' => ['//evil.example.com'],
    'ftp' => ['ftp://example.com/file'],
]);

test('القوائم تُعرض من menu_items بترتيبها، وعنصر صفحة غير منشورة لا يظهر حتى تُنشر', function (): void {
    $actor = contentManager();
    $draft = app(CreatePage::class)->handle($actor, pageInput());

    app(SaveMenus::class)->handle($actor, [
        'header' => [
            ['label' => 'من نحن', 'page_id' => $draft->id],
            ['label' => 'واتساب', 'url' => 'https://wa.me/966501234567'],
        ],
        'footer' => [['label' => 'المبادرات', 'url' => '/beneficiaries']],
    ]);

    expect(app(SiteMenu::class)->links(MenuLocation::Header))->toBe([
        ['label' => 'واتساب', 'url' => 'https://wa.me/966501234567', 'external' => true],
    ]);

    app(PublishPage::class)->handle($actor, $draft);

    expect(app(SiteMenu::class)->links(MenuLocation::Header))->toBe([
        ['label' => 'من نحن', 'url' => '/about-us', 'external' => false],
        ['label' => 'واتساب', 'url' => 'https://wa.me/966501234567', 'external' => true],
    ]);

    $this->get('/about-us')
        ->assertOk()
        ->assertSeeInOrder(['data-menu="header"', 'من نحن', 'واتساب'], false)
        ->assertSee('rel="noopener noreferrer"', false)
        ->assertSeeInOrder(['data-menu="footer"', 'المبادرات'], false);
});

test('شاشة القوائم تحفظ نسخة جديدة وتسجّل في التدقيق', function (): void {
    $actor = contentManager();
    Filament::setCurrentPanel('admin');

    Livewire::actingAs($actor)
        ->test(Menus::class)
        ->fillForm([
            'header' => [['label' => 'تواصل', 'target' => 'url', 'url' => 'tel:+966501234567']],
            'footer' => [],
        ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified(__('pages.menus.saved'));

    $revision = MenuRevision::query()->latest('id')->firstOrFail();

    expect($revision->items)->toEqual([['location' => 'header', 'label' => 'تواصل', 'page_id' => null, 'url' => 'tel:+966501234567']])
        ->and(AuditLog::query()->where('action', SaveMenus::AUDIT_ACTION)->where('actor_id', $actor->id)->exists())->toBeTrue();
});

test('شاشة إنشاء الصفحة تحفظ مسودة بكتل المنشئ، وتُظهر خطأ الآيبان تحت حقله', function (): void {
    $actor = contentManager();
    Filament::setCurrentPanel('admin');

    $rejected = Livewire::actingAs($actor)
        ->test(CreatePageScreen::class)
        ->fillForm([
            'title' => 'صفحة من اللوحة',
            'slug' => 'from-panel',
            'blocks' => [
                ['type' => PageBlocks::STEPS, 'data' => ['heading' => 'الخطوات', 'items' => [['title' => 'حوّل إلى '.SaudiIban::fromParts('80', '000000123456789012')]]]],
            ],
        ])
        ->call('create');

    $ibanErrors = collect($rejected->errors()->messages())
        ->filter(fn (array $messages, string $key): bool => preg_match('/^data\.blocks\.[^.]+\.data\.items\.[^.]+\.title$/', $key) === 1);

    expect($ibanErrors)->toHaveCount(1)
        ->and($ibanErrors->first())->toContain(__('pages.validation.iban_in_text'))
        ->and(Page::query()->where('slug', 'from-panel')->exists())->toBeFalse();

    Livewire::actingAs($actor)
        ->test(CreatePageScreen::class)
        ->fillForm([
            'title' => 'صفحة من اللوحة',
            'slug' => 'from-panel',
            'blocks' => [
                ['type' => PageBlocks::STEPS, 'data' => ['heading' => 'الخطوات', 'items' => [['title' => 'اختر مبادرة']]]],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::query()->where('slug', 'from-panel')->sole();

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->blocks[0]['type'])->toBe(PageBlocks::STEPS)
        ->and($page->blocks[0]['data']['items'][0]['title'])->toBe('اختر مبادرة')
        ->and(AuditLog::query()->where('action', CreatePage::AUDIT_ACTION)->exists())->toBeTrue();
});

test('خريطة الموقع تضم الرئيسية والمبادرات والصفحات المنشورة فقط', function (): void {
    Page::factory()->published()->create(['slug' => 'published-page']);
    Page::factory()->create(['slug' => 'draft-page']);

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee('<loc>'.route('home').'</loc>', false)
        ->assertSee('<loc>'.route('beneficiaries.index').'</loc>', false)
        ->assertSee('<loc>'.url('/published-page').'</loc>', false)
        ->assertDontSee('draft-page')
        ->assertDontSee('/admin')
        ->assertDontSee('/login');
});
