<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Page;
use App\PageStatus;
use App\Support\PageBlocks;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * صفحتا سياسة الخصوصية والشروط والأحكام (T19 — docs/SPEC.md §12.11, §12.12).
 *
 * تُنشآن بمسودة عربية من lang/ar/legal.php عند أول استخدام، وتحملان تنبيه "بحاجة
 * إلى مراجعة قانونية" ضمن محتواهما المنشور. بعد الإنشاء هما صفحتان عاديتان
 * (is_system=false) قابلتان للتعديل والنشر من منشئ الصفحات كأي صفحة أخرى، خلافًا
 * للرئيسية التي is_system عندها ثابت المسار (App\Services\BaseDesign).
 */
class LegalPages
{
    public const string PRIVACY_SLUG = 'privacy-policy';

    public const string TERMS_SLUG = 'terms';

    public function privacy(): Page
    {
        return $this->findOrCreate(
            self::PRIVACY_SLUG,
            (string) __('legal.privacy.title'),
            (string) __('legal.privacy.seo_description'),
            'legal.privacy.sections',
        );
    }

    public function terms(): Page
    {
        return $this->findOrCreate(
            self::TERMS_SLUG,
            (string) __('legal.terms.title'),
            (string) __('legal.terms.seo_description'),
            'legal.terms.sections',
        );
    }

    private function findOrCreate(string $slug, string $title, string $seoDescription, string $sectionsKey): Page
    {
        $page = $this->find($slug);

        if ($page instanceof Page) {
            return $page;
        }

        try {
            return DB::transaction(function () use ($slug, $title, $seoDescription, $sectionsKey): Page {
                $blocks = $this->blocks($sectionsKey);

                $page = new Page;
                $page->forceFill([
                    'slug' => $slug,
                    'title' => $title,
                    'seo_description' => $seoDescription,
                    'blocks' => $blocks,
                    'status' => PageStatus::Published,
                    'is_system' => false,
                    'published_at' => now(),
                ])->save();

                $page->revisions()->create(['blocks' => $blocks]);

                return $page;
            });
        } catch (UniqueConstraintViolationException) {
            // سباق نادر: طلب آخر أنشأ الصفحة في اللحظة نفسها.
            return $this->find($slug) ?? throw new RuntimeException("Legal page [{$slug}] is missing.");
        }
    }

    private function find(string $slug): ?Page
    {
        return Page::query()->where('slug', $slug)->first();
    }

    /**
     * تنبيه المراجعة القانونية كأول كتلة، ثم فقرات المسودة من lang/ar/legal.php.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    private function blocks(string $sectionsKey): array
    {
        /** @var list<array{heading: string, body: string}> $sections */
        $sections = __($sectionsKey);

        $notice = ['heading' => __('legal.notice_heading'), 'body' => '<p><strong>'.e(__('legal.notice')).'</strong></p>'];

        return PageBlocks::validate(array_map(
            fn (array $section): array => ['type' => PageBlocks::RICH_TEXT, 'data' => $section],
            [$notice, ...$sections],
        ));
    }
}
