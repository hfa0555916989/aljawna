<?php

declare(strict_types=1);

namespace App\Services;

use App\MenuLocation;
use App\Models\MenuItem;
use App\Models\MenuRevision;
use App\Models\Page;
use App\PageStatus;
use App\Support\PageBlocks;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * "التصميم الأساسي" (قرار المالك في docs/DECISIONS.md): نسخة افتراضية للصفحة الرئيسية
 * وقائمتي الرأس والتذييل، تُحفظ عند أول استخدام كنسخة أساس محمية (is_baseline) في
 * page_revisions وmenu_revisions، وهما للإضافة فقط فلا تُعدَّل نسخة الأساس ولا تُحذف.
 *
 * الاستعادة (App\Actions\Content\RestoreBaseDesign) تنسخ نسخة الأساس المحفوظة
 * في قاعدة البيانات إلى نسخة جديدة، فتبقى النسخ السابقة ويمكن التراجع إليها.
 */
class BaseDesign
{
    public const string HOME_SLUG = 'home';

    /**
     * الصفحة الرئيسية النظامية، تُنشأ بالتصميم الأساسي ونسخة أساسه إن لم توجد.
     */
    public function home(): Page
    {
        $home = $this->findHome();

        if ($home instanceof Page) {
            return $home;
        }

        try {
            return DB::transaction(function (): Page {
                $blocks = $this->homeBlocks();

                $home = new Page;
                $home->forceFill([
                    'slug' => self::HOME_SLUG,
                    'title' => config('app.name'),
                    'seo_description' => __('site.meta.description'),
                    'blocks' => $blocks,
                    'status' => PageStatus::Published,
                    'is_system' => true,
                    'published_at' => now(),
                ])->save();

                $home->revisions()->create(['blocks' => $blocks, 'is_baseline' => true]);

                return $home;
            });
        } catch (UniqueConstraintViolationException) {
            // سباق نادر: طلب آخر أنشأ الرئيسية في اللحظة نفسها.
            return $this->findHome() ?? throw new RuntimeException('Home page is missing.');
        }
    }

    /**
     * نسخة الأساس للقائمتين، تُنشأ مع عناصرها الافتراضية إن لم توجد.
     */
    public function menuBaseline(): MenuRevision
    {
        $baseline = MenuRevision::query()->where('is_baseline', true)->first();

        if ($baseline instanceof MenuRevision) {
            return $baseline;
        }

        $items = $this->menuItems();

        try {
            return DB::transaction(function () use ($items): MenuRevision {
                $baseline = MenuRevision::query()->create(['items' => $items, 'is_baseline' => true]);

                if (MenuItem::query()->doesntExist()) {
                    SiteMenu::replace($items);
                }

                return $baseline;
            });
        } catch (UniqueConstraintViolationException) {
            return MenuRevision::query()->where('is_baseline', true)->firstOrFail();
        }
    }

    /**
     * كتل الرئيسية في التصميم الأساسي: كل نصوصها من lang/ar فلا نص حر فيها.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function homeBlocks(): array
    {
        /** @var list<string> $about */
        $about = __('site.home.about');

        /** @var list<array{title: string, body: string}> $steps */
        $steps = __('site.home.steps');

        /** @var list<array{question: string, answer: string}> $faq */
        $faq = __('pages.base.faq');

        $aboutHtml = '<ul>'.implode('', array_map(fn (string $line): string => '<li>'.e($line).'</li>', $about)).'</ul>';

        return PageBlocks::validate([
            ['type' => PageBlocks::HERO, 'data' => [
                'title' => config('app.name'),
                'lead' => __('site.home.lead'),
                'show_counters' => true,
                'buttons' => [
                    ['label' => __('site.home.browse'), 'url' => '/beneficiaries', 'style' => 'primary', 'guests_only' => false],
                    ['label' => __('site.home.register'), 'url' => '/register', 'style' => 'secondary', 'guests_only' => true],
                ],
            ]],
            ['type' => PageBlocks::INITIATIVES, 'data' => [
                'heading' => __('pages.base.available_heading'),
                'mode' => PageBlocks::INITIATIVES_AVAILABLE,
                'limit' => 3,
            ]],
            ['type' => PageBlocks::RICH_TEXT, 'data' => [
                'heading' => __('site.home.about_heading'),
                'body' => $aboutHtml,
            ]],
            ['type' => PageBlocks::STEPS, 'data' => [
                'heading' => __('site.home.how_heading'),
                'items' => $steps,
            ]],
            ['type' => PageBlocks::FAQ, 'data' => [
                'heading' => __('pages.base.faq_heading'),
                'items' => $faq,
            ]],
            ['type' => PageBlocks::INITIATIVES, 'data' => [
                'heading' => __('site.home.latest_heading'),
                'mode' => PageBlocks::INITIATIVES_LATEST,
                'limit' => 3,
            ]],
        ]);
    }

    /**
     * عناصر القائمتين في التصميم الأساسي بترتيبها.
     *
     * @return list<array{location: string, label: string, page_id: int|null, url: string|null}>
     */
    public function menuItems(): array
    {
        $homeId = $this->home()->id;
        $items = [];

        foreach (MenuLocation::cases() as $location) {
            $items[] = ['location' => $location->value, 'label' => (string) __('pages.base.menu.home'), 'page_id' => $homeId, 'url' => null];
            $items[] = ['location' => $location->value, 'label' => (string) __('pages.base.menu.beneficiaries'), 'page_id' => null, 'url' => '/beneficiaries'];
        }

        return $items;
    }

    private function findHome(): ?Page
    {
        return Page::query()->where('is_system', true)->where('slug', self::HOME_SLUG)->first();
    }
}
