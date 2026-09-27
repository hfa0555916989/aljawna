<?php

declare(strict_types=1);

namespace App\Services;

use App\MenuLocation;
use App\Models\MenuItem;
use App\Support\SafeLink;
use Illuminate\Support\Facades\Cache;

/**
 * روابط قائمتي الرأس والتذييل من menu_items (docs/SPEC.md FR-54).
 *
 * تُخزَّن مؤقتًا بلا انتهاء وتُبطل عند حفظ القوائم أو أي صفحة. العنصر المشير إلى
 * صفحة غير منشورة لا يظهر. روابط الدخول والتسجيل ولوحتي تبقى نظامية في القالب.
 */
class SiteMenu
{
    private const string CACHE_KEY = 'site-menu';

    /**
     * @return list<array{label: string, url: string, external: bool}>
     */
    public function links(MenuLocation $location): array
    {
        /** @var array<string, list<array{label: string, url: string, external: bool}>> $links */
        $links = Cache::rememberForever(self::CACHE_KEY, fn (): array => $this->resolve());

        return $links[$location->value] ?? [];
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * يستبدل عناصر القائمتين بالعناصر المعطاة (بترتيبها داخل كل موضع).
     *
     * @param  list<array{location: string, label: string, page_id: int|null, url: string|null}>  $items
     */
    public static function replace(array $items): void
    {
        MenuItem::query()->delete();

        $positions = [];

        foreach ($items as $item) {
            $positions[$item['location']] = ($positions[$item['location']] ?? 0) + 1;

            MenuItem::query()->create([...$item, 'position' => $positions[$item['location']]]);
        }

        self::forget();
    }

    /**
     * @return array<string, list<array{label: string, url: string, external: bool}>>
     */
    private function resolve(): array
    {
        app(BaseDesign::class)->menuBaseline();

        $links = [];

        $items = MenuItem::query()
            ->with('page:id,slug,status,is_system')
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $url = $item->url;

            if ($item->page_id !== null) {
                if ($item->page === null || ! $item->page->isPublished()) {
                    continue;
                }

                $url = $item->page->is_system ? '/' : route('pages.show', ['slug' => $item->page->slug], absolute: false);
            }

            if ($url === null) {
                continue;
            }

            $links[$item->location->value][] = ['label' => $item->label, 'url' => $url, 'external' => SafeLink::isExternal($url)];
        }

        return $links;
    }
}
