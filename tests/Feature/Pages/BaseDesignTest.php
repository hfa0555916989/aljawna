<?php

declare(strict_types=1);

use App\Actions\Content\PublishPage;
use App\Actions\Content\RestoreBaseDesign;
use App\Actions\Content\RestoreMenuRevision;
use App\Actions\Content\RestorePageRevision;
use App\Actions\Content\SaveMenus;
use App\Actions\Content\SavePageDraft;
use App\Filament\Resources\Pages\Pages\ListPages;
use App\MenuLocation;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\MenuRevision;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\Services\BaseDesign;
use App\Services\SiteMenu;
use App\Support\PageBlocks;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/*
|--------------------------------------------------------------------------
| التصميم الأساسي واستعادته (قرار المالك في docs/DECISIONS.md)
|--------------------------------------------------------------------------
| نسخة أساس محمية للرئيسية وللقائمتين، وزر استعادة بـ content.manage ينشئ نسخًا
| جديدة ولا يحذف ما قبلها فيمكن التراجع، ويُكتب في audit_logs.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake((string) config('security.branding.disk'));
});

/**
 * يعدّل الرئيسية وينشرها، ويستبدل القائمتين، ليبتعد الموقع عن التصميم الأساسي.
 */
function customizeSite(User $actor): void
{
    $home = app(BaseDesign::class)->home();
    $hero = sampleBlocks()[PageBlocks::HERO];
    $hero['data']['title'] = 'رئيسية معدّلة';

    app(SavePageDraft::class)->handle($actor, $home, ['title' => $home->title, 'slug' => $home->slug, 'blocks' => [$hero]]);
    app(PublishPage::class)->handle($actor, $home->refresh());

    app(SaveMenus::class)->handle($actor, [
        'header' => [['label' => 'رابط معدّل', 'url' => '/beneficiaries']],
        'footer' => [],
    ]);
}

test('أول زيارة تثبّت الرئيسية بالتصميم الأساسي ونسخة أساسها والقائمتين', function (): void {
    expect(Page::query()->count())->toBe(0);

    $this->get('/')
        ->assertOk()
        ->assertSee(__('site.home.lead'))
        ->assertSee(__('pages.base.faq_heading'))
        ->assertSee(__('pages.base.menu.beneficiaries'));

    $home = Page::query()->sole();

    expect($home->is_system)->toBeTrue()
        ->and($home->slug)->toBe(BaseDesign::HOME_SLUG)
        ->and($home->isPublished())->toBeTrue()
        ->and($home->revisions()->sole()->is_baseline)->toBeTrue()
        ->and($home->blocks)->toEqual(app(BaseDesign::class)->homeBlocks())
        ->and(MenuRevision::query()->sole()->is_baseline)->toBeTrue()
        ->and(MenuItem::query()->count())->toBe(4);

    expect(app(SiteMenu::class)->links(MenuLocation::Header))->toBe([
        ['label' => __('pages.base.menu.home'), 'url' => '/', 'external' => false],
        ['label' => __('pages.base.menu.beneficiaries'), 'url' => '/beneficiaries', 'external' => false],
    ]);
});

test('التثبيت لا يتكرر: زيارات متتالية لا تنشئ رئيسية أو نسخة أساس ثانية', function (): void {
    $this->get('/')->assertOk();
    $this->get('/')->assertOk();
    app(BaseDesign::class)->home();
    app(BaseDesign::class)->menuBaseline();

    expect(Page::query()->count())->toBe(1)
        ->and(PageRevision::query()->where('is_baseline', true)->count())->toBe(1)
        ->and(MenuRevision::query()->where('is_baseline', true)->count())->toBe(1);
});

test('الاستعادة تعيد الرئيسية والقائمتين إلى التصميم الأساسي كنسخ جديدة دون حذف ما قبلها', function (): void {
    $actor = contentManager();
    customizeSite($actor);

    $this->get('/')->assertSee('رئيسية معدّلة')->assertSee('رابط معدّل');

    $home = app(BaseDesign::class)->home();
    $pageRevisionsBefore = $home->revisions()->pluck('id')->all();
    $menuRevisionsBefore = MenuRevision::query()->pluck('id')->all();

    $result = app(RestoreBaseDesign::class)->handle($actor);

    expect($home->revisions()->pluck('id')->all())->toBe([...$pageRevisionsBefore, $result['page_revision']->id])
        ->and(MenuRevision::query()->pluck('id')->all())->toBe([...$menuRevisionsBefore, $result['menu_revision']->id])
        ->and($result['page_revision']->is_baseline)->toBeFalse()
        ->and($result['page_revision']->author_id)->toBe($actor->id)
        ->and($home->refresh()->blocks)->toEqual($home->baselineRevision()->sole()->blocks);

    $this->get('/')
        ->assertOk()
        ->assertSee(__('site.home.lead'))
        ->assertDontSee('رئيسية معدّلة')
        ->assertDontSee('رابط معدّل')
        ->assertSee(__('pages.base.menu.beneficiaries'));
});

test('يمكن التراجع عن الاستعادة باسترجاع النسخة السابقة للرئيسية والقائمتين', function (): void {
    $actor = contentManager();
    customizeSite($actor);
    $home = app(BaseDesign::class)->home();

    app(RestoreBaseDesign::class)->handle($actor);

    $audit = AuditLog::query()->where('action', RestoreBaseDesign::AUDIT_ACTION)->sole();
    $previousPage = PageRevision::query()->findOrFail($audit->meta['previous_page_revision_id']);
    $previousMenu = MenuRevision::query()->findOrFail($audit->meta['previous_menu_revision_id']);

    app(RestorePageRevision::class)->handle($actor, $home->refresh(), $previousPage);
    app(RestoreMenuRevision::class)->handle($actor, $previousMenu);

    $this->get('/')->assertSee('رئيسية معدّلة')->assertSee('رابط معدّل');

    expect($home->baselineRevision()->sole()->blocks)->toEqual(app(BaseDesign::class)->homeBlocks());
});

test('الاستعادة تُكتب في سجل التدقيق مع أرقام النسخ الجديدة والسابقة', function (): void {
    $actor = contentManager();
    customizeSite($actor);

    $result = app(RestoreBaseDesign::class)->handle($actor);
    $home = app(BaseDesign::class)->home();

    $audit = AuditLog::query()->where('action', RestoreBaseDesign::AUDIT_ACTION)->sole();

    expect($audit->actor_id)->toBe($actor->id)
        ->and($audit->subject_id)->toBe($home->id)
        ->and($audit->meta['page_revision_id'])->toBe($result['page_revision']->id)
        ->and($audit->meta['menu_revision_id'])->toBe($result['menu_revision']->id)
        ->and($audit->meta['previous_page_revision_id'])->toBeLessThan($result['page_revision']->id)
        ->and($audit->meta['previous_menu_revision_id'])->toBeLessThan($result['menu_revision']->id);
});

test('من لا يملك content.manage لا يستعيد التصميم الأساسي ولا يتغير شيء', function (User $actor): void {
    customizeSite(contentManager());
    $pageRevisions = PageRevision::query()->count();
    $menuRevisions = MenuRevision::query()->count();

    expect(fn () => app(RestoreBaseDesign::class)->handle($actor))->toThrow(AuthorizationException::class);

    expect(PageRevision::query()->count())->toBe($pageRevisions)
        ->and(MenuRevision::query()->count())->toBe($menuRevisions)
        ->and(AuditLog::query()->where('action', RestoreBaseDesign::AUDIT_ACTION)->exists())->toBeFalse();

    $this->get('/')->assertSee('رئيسية معدّلة');
})->with([
    'مشرف بلا الصلاحية' => fn (): User => User::factory()->supervisor()->withPermissions(['beneficiaries.view', 'settings.manage'])->create(),
    'مبادر' => fn (): User => User::factory()->create(),
    'مدير معطّل' => fn (): User => User::factory()->admin()->inactive()->create(),
]);

test('زر الاستعادة في اللوحة يطلب تأكيدًا ويستعيد التصميم الأساسي لمن يملك content.manage', function (): void {
    $actor = contentManager();
    customizeSite($actor);
    Filament::setCurrentPanel('admin');

    Livewire::actingAs($actor)
        ->test(ListPages::class)
        ->assertActionVisible('restoreBaseDesign')
        ->mountAction('restoreBaseDesign')
        ->assertActionMounted('restoreBaseDesign')
        ->assertSee(__('pages.base.restore.heading'))
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified(__('pages.base.restore.done'));

    expect(AuditLog::query()->where('action', RestoreBaseDesign::AUDIT_ACTION)->count())->toBe(1);
    $this->get('/')->assertDontSee('رئيسية معدّلة');
});

test('زر الاستعادة مخفي عن مشرف بلا content.manage والوصول إلى صفحة القائمة 403', function (): void {
    $supervisor = User::factory()->supervisor()->withPermissions(['beneficiaries.view'])->create();

    $this->actingAs($supervisor)->get('/admin/content/pages')->assertForbidden();
});

test('نسخة الأساس للرئيسية لا تُعدَّل ولا تُحذف ولو بـ SQL مباشر', function (string $sql): void {
    $baseline = app(BaseDesign::class)->home()->baselineRevision()->sole();
    $blocks = $baseline->blocks;

    expect(fn () => DB::transaction(fn () => DB::statement($sql, [$baseline->id])))
        ->toThrow(QueryException::class, 'page_revisions is append-only');

    expect($baseline->refresh()->blocks)->toEqual($blocks)
        ->and($baseline->is_baseline)->toBeTrue();
})->with([
    'تعديل الكتل' => ["UPDATE page_revisions SET blocks = '[]' WHERE id = ?"],
    'إلغاء كونها أساسًا' => ['UPDATE page_revisions SET is_baseline = false WHERE id = ?'],
    'حذف' => ['DELETE FROM page_revisions WHERE id = ?'],
]);

test('نسخة الأساس للقائمتين لا تُعدَّل ولا تُحذف ولو بـ SQL مباشر', function (string $sql): void {
    $baseline = app(BaseDesign::class)->menuBaseline();
    $items = $baseline->items;

    expect(fn () => DB::transaction(fn () => DB::statement($sql, [$baseline->id])))
        ->toThrow(QueryException::class, 'menu_revisions is append-only');

    expect($baseline->refresh()->items)->toEqual($items);
})->with([
    'تعديل العناصر' => ["UPDATE menu_revisions SET items = '[]' WHERE id = ?"],
    'حذف' => ['DELETE FROM menu_revisions WHERE id = ?'],
]);

test('نسخة الأساس لا تُعدَّل عبر Eloquent ولا يمكن إنشاء نسخة أساس ثانية', function (): void {
    $home = app(BaseDesign::class)->home();
    $baseline = $home->baselineRevision()->sole();

    expect(fn () => DB::transaction(fn () => $baseline->forceFill(['blocks' => []])->save()))
        ->toThrow(QueryException::class, 'append-only');

    expect(fn () => DB::transaction(fn () => $home->revisions()->create(['blocks' => [], 'is_baseline' => true])))
        ->toThrow(QueryException::class);

    app(BaseDesign::class)->menuBaseline();
    expect(fn () => DB::transaction(fn () => MenuRevision::query()->create(['items' => [], 'is_baseline' => true])))
        ->toThrow(QueryException::class);

    expect(PageRevision::query()->where('is_baseline', true)->count())->toBe(1)
        ->and(MenuRevision::query()->where('is_baseline', true)->count())->toBe(1);
});

test('الرئيسية النظامية لا تُحذف', function (): void {
    $home = app(BaseDesign::class)->home();

    expect(fn () => DB::transaction(fn () => $home->delete()))->toThrow(QueryException::class, 'pages is append-only');
    expect(User::factory()->admin()->create()->can('delete', $home))->toBeFalse();
});
