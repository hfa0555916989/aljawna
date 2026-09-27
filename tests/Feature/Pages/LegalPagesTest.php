<?php

declare(strict_types=1);

use App\Actions\Content\PublishPage;
use App\Actions\Content\SavePageDraft;
use App\Models\Page;
use App\Services\LegalPages;
use App\Support\ReservedSlugs;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| صفحتا سياسة الخصوصية والشروط والأحكام (T19 — docs/SPEC.md §12.11, §12.12)
|--------------------------------------------------------------------------
| مسودتان عربيتان بتنبيه "بحاجة إلى مراجعة قانونية"، عاديتان (is_system=false)
| فقابلتان للتحرير من منشئ الصفحات، وروابطهما في التذييل (App\Services\BaseDesign).
*/

beforeEach(function (): void {
    $this->seed(PermissionSeeder::class);
    Storage::fake((string) config('security.branding.disk'));
});

test('صفحتا الخصوصية والشروط تحملان مسودة عربية وتنبيه المراجعة القانونية', function (): void {
    $privacy = app(LegalPages::class)->privacy();
    $terms = app(LegalPages::class)->terms();

    $this->get('/'.$privacy->slug)
        ->assertOk()
        ->assertSee(__('legal.privacy.title'))
        ->assertSee(__('legal.notice_heading'))
        ->assertSee(__('legal.notice'));

    $this->get('/'.$terms->slug)
        ->assertOk()
        ->assertSee(__('legal.terms.title'))
        ->assertSee(__('legal.notice_heading'))
        ->assertSee(__('legal.notice'));

    expect($privacy->blocks[0]['data']['heading'])->toBe(__('legal.notice_heading'))
        ->and($terms->blocks[0]['data']['heading'])->toBe(__('legal.notice_heading'));
});

test('رابط مباشر لصفحة الخصوصية أو الشروط يعمل حتى قبل أي زيارة سابقة للرئيسية', function (string $slug): void {
    expect(Page::query()->count())->toBe(0);

    $this->get('/'.$slug)->assertOk();

    expect(Page::query()->where('slug', $slug)->sole()->isPublished())->toBeTrue();
})->with([
    'الخصوصية' => [LegalPages::PRIVACY_SLUG],
    'الشروط' => [LegalPages::TERMS_SLUG],
]);

test('مساري الخصوصية والشروط غير محجوزين وبصيغة صالحة لمسار صفحة', function (): void {
    expect(ReservedSlugs::isReserved(LegalPages::PRIVACY_SLUG))->toBeFalse()
        ->and(ReservedSlugs::isReserved(LegalPages::TERMS_SLUG))->toBeFalse()
        ->and(ReservedSlugs::isValidFormat(LegalPages::PRIVACY_SLUG))->toBeTrue()
        ->and(ReservedSlugs::isValidFormat(LegalPages::TERMS_SLUG))->toBeTrue();
});

test('صفحتا الخصوصية والشروط عاديتان: قابلتان للتعديل والنشر من المنشئ ولا تُحذفان', function (): void {
    $actor = contentManager();
    $privacy = app(LegalPages::class)->privacy();

    expect($privacy->is_system)->toBeFalse()
        ->and($actor->can('update', $privacy))->toBeTrue()
        ->and($actor->can('delete', $privacy))->toBeFalse();

    app(SavePageDraft::class)->handle($actor, $privacy, [
        'title' => $privacy->title,
        'slug' => $privacy->slug,
        'blocks' => [['type' => 'rich_text', 'data' => ['heading' => 'تحديث', 'body' => '<p>نص محدَّث بعد المراجعة القانونية.</p>']]],
    ]);
    app(PublishPage::class)->handle($actor, $privacy->refresh());

    $this->get('/'.$privacy->slug)->assertSee('نص محدَّث بعد المراجعة القانونية.');
});
