<?php

declare(strict_types=1);

use App\Actions\Content\CreatePage;
use App\Actions\Content\PublishPage;
use App\Actions\Content\RestorePageRevision;
use App\Actions\Content\SavePageDraft;
use App\Actions\Content\UnpublishPage;
use App\Models\AuditLog;
use App\Models\Beneficiary;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use App\PageStatus;
use App\Services\BaseDesign;
use App\Support\PageBlocks;
use Database\Seeders\PermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| مسودة ومعاينة ونشر وسجل نسخ (T17 — FR-52)
|--------------------------------------------------------------------------
| المعاينة لا تنشر، والنشر يكتب نسخة، والاسترجاع يعيد نسخة سابقة كنسخة جديدة.
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake((string) config('security.branding.disk'));
});

function heroTitled(string $title): array
{
    $hero = sampleBlocks()[PageBlocks::HERO];
    $hero['data']['title'] = $title;

    return $hero;
}

function saveDraftTitled(Page $page, string $title): Page
{
    return app(SavePageDraft::class)->handle(contentManager(), $page, [
        'title' => $page->title,
        'slug' => $page->slug,
        'blocks' => [heroTitled($title)],
    ]);
}

test('الصفحة الجديدة مسودة لا يراها الزوار ولا نسخ لها', function (): void {
    $page = app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [heroTitled('مسودة أولى')]]));

    expect($page->status)->toBe(PageStatus::Draft)
        ->and($page->revisions()->count())->toBe(0);

    $this->get('/about-us')->assertNotFound();
});

test('المعاينة تعرض المسودة لمن يملك content.manage دون نشر أو كتابة نسخة', function (): void {
    $page = app(CreatePage::class)->handle(contentManager(), pageInput(['blocks' => [heroTitled('مسودة للمعاينة')]]));

    $this->actingAs(contentManager())
        ->get(route('admin.pages.preview', $page))
        ->assertOk()
        ->assertSee('مسودة للمعاينة')
        ->assertSee(__('pages.preview.banner'))
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

    expect($page->refresh()->status)->toBe(PageStatus::Draft)
        ->and($page->revisions()->count())->toBe(0);

    auth()->logout();
    $this->get('/about-us')->assertNotFound();
});

test('النشر يكتب نسخة جديدة ويجعلها ما يراه الزوار', function (): void {
    $actor = contentManager();
    $page = app(CreatePage::class)->handle($actor, pageInput(['blocks' => [heroTitled('المحتوى المنشور')]]));

    $revision = app(PublishPage::class)->handle($actor, $page);

    expect($page->refresh()->status)->toBe(PageStatus::Published)
        ->and($page->published_at)->not->toBeNull()
        ->and($page->revisions()->count())->toBe(1)
        ->and($revision->author_id)->toBe($actor->id)
        ->and($revision->blocks)->toEqual($page->blocks);

    $this->get('/about-us')
        ->assertOk()
        ->assertSee('المحتوى المنشور')
        ->assertSee('<title>'.e('من نحن — '.config('app.name')).'</title>', false)
        ->assertSee('<meta name="description" content="'.e('تعريف قصير بالمبادرة.').'">', false)
        ->assertDontSee('noindex', false);
});

test('تعديل مسودة صفحة منشورة لا يغيّر ما يراه الزوار حتى النشر، والمعاينة تعرض التعديل', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'about-us', 'blocks' => [heroTitled('النسخة المنشورة')]]);

    saveDraftTitled($page, 'تعديل غير منشور');

    $this->get('/about-us')->assertOk()->assertSee('النسخة المنشورة')->assertDontSee('تعديل غير منشور');
    $this->actingAs(contentManager())->get(route('admin.pages.preview', $page))->assertSee('تعديل غير منشور');

    expect($page->revisions()->count())->toBe(1);

    app(PublishPage::class)->handle(contentManager(), $page->refresh());

    auth()->logout();
    $this->get('/about-us')->assertSee('تعديل غير منشور')->assertDontSee('النسخة المنشورة');
    expect($page->revisions()->count())->toBe(2);
});

test('الاسترجاع يعيد نسخة سابقة كنسخة جديدة ولا يحذف أي نسخة', function (): void {
    $actor = contentManager();
    $page = Page::factory()->published()->create(['slug' => 'about-us', 'blocks' => [heroTitled('الإصدار الأول')]]);
    $first = $page->revisions()->sole();

    saveDraftTitled($page, 'الإصدار الثاني');
    $second = app(PublishPage::class)->handle($actor, $page->refresh());

    $restored = app(RestorePageRevision::class)->handle($actor, $page->refresh(), $first);

    expect($page->revisions()->orderBy('id')->pluck('id')->all())->toBe([$first->id, $second->id, $restored->id])
        ->and($restored->blocks)->toBe($first->blocks)
        ->and($page->refresh()->blocks)->toBe($first->blocks);

    $this->get('/about-us')->assertSee('الإصدار الأول')->assertDontSee('الإصدار الثاني');
});

test('لا يُسترجع إلى صفحة نسخةٌ تخص صفحة أخرى', function (): void {
    $page = Page::factory()->published()->create();
    $other = Page::factory()->published()->create();

    expect(fn () => app(RestorePageRevision::class)->handle(contentManager(), $page, $other->revisions()->sole()))
        ->toThrow(AuthorizationException::class);

    expect($page->revisions()->count())->toBe(1);
});

test('إلغاء النشر يخفي الصفحة ويُبقي نسخها، والرئيسية لا يُلغى نشرها ولو للمدير', function (): void {
    $page = Page::factory()->published()->create(['slug' => 'about-us']);

    app(UnpublishPage::class)->handle(contentManager(), $page);

    expect($page->refresh()->status)->toBe(PageStatus::Draft)
        ->and($page->revisions()->count())->toBe(1);
    $this->get('/about-us')->assertNotFound();

    $home = app(BaseDesign::class)->home();
    $admin = User::factory()->admin()->create();

    expect(fn () => app(UnpublishPage::class)->handle($admin, $home))->toThrow(AuthorizationException::class);
    expect($home->refresh()->status)->toBe(PageStatus::Published);
});

test('سجل نسخ الصفحات للإضافة فقط: لا تعديل ولا حذف ولو بـ SQL مباشر', function (string $sql): void {
    Page::factory()->published()->create();

    expect(fn () => DB::transaction(fn () => DB::statement($sql)))
        ->toThrow(QueryException::class, 'page_revisions is append-only');

    expect(PageRevision::query()->count())->toBe(1);
})->with([
    'UPDATE' => ["UPDATE page_revisions SET blocks = '[]'"],
    'DELETE' => ['DELETE FROM page_revisions'],
    'TRUNCATE' => ['TRUNCATE page_revisions CASCADE'],
]);

test('الصفحات لا تُحذف لأي أحد: السياسة تمنع المدير، وقاعدة البيانات ترفض الحذف', function (): void {
    $page = Page::factory()->create();
    $admin = User::factory()->admin()->create();

    expect($admin->can('delete', $page))->toBeFalse()
        ->and($admin->can('deleteAny', Page::class))->toBeFalse();

    expect(fn () => DB::transaction(fn () => $page->delete()))->toThrow(QueryException::class, 'pages is append-only');
    expect(Page::query()->whereKey($page->id)->exists())->toBeTrue();
});

test('كل حفظ ونشر واسترجاع وإلغاء نشر يُكتب في سجل التدقيق', function (): void {
    $actor = contentManager();
    $page = app(CreatePage::class)->handle($actor, pageInput());
    saveDraftTitled($page, 'تعديل');
    $revision = app(PublishPage::class)->handle($actor, $page->refresh());
    app(RestorePageRevision::class)->handle($actor, $page, $revision);
    app(UnpublishPage::class)->handle($actor, $page->refresh());

    expect(AuditLog::query()->where('subject_type', $page->getMorphClass())->where('subject_id', $page->id)->orderBy('id')->pluck('action')->all())
        ->toBe(['page.created', 'page.draft_saved', 'page.published', 'page.revision_restored', 'page.unpublished'])
        ->and(AuditLog::query()->where('action', 'page.published')->sole()->actor_id)->toBe($actor->id);
});

test('الصفحة المنشورة تعرض كل أنواع الكتل، وكتلة المبادرات بلا أي بيانات بنكية', function (): void {
    $beneficiary = Beneficiary::factory()->approved()->create(['display_name' => 'سالم ماجد تركي العجاوني']);

    $page = Page::factory()->published()->create(['slug' => 'all-blocks', 'blocks' => array_values(sampleBlocks())]);

    $response = $this->get('/all-blocks')
        ->assertOk()
        ->assertSee('عنوان الصفحة')
        ->assertSee('<strong>منسّق</strong>', false)
        ->assertSee('صورة توضيحية')
        ->assertSee('https://wa.me/966501234567', false)
        ->assertSee('الخطوة الأولى')
        ->assertSee('سؤال؟')
        ->assertSee('data-metric="available"', false)
        ->assertSee('<hr', false)
        ->assertSee('سالم ماجد تركي العجاوني');

    foreach (bankSecretsOf($beneficiary) as $secret) {
        if ($secret !== $beneficiary->display_name) {
            $response->assertDontSee($secret);
        }
    }

    expect($page->revisions()->count())->toBe(1);
});
