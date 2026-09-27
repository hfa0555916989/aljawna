<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\MenuLocation;
use App\Models\Page;
use App\Support\BankDetailsInText;
use App\Support\SafeLink;
use Illuminate\Validation\ValidationException;

/**
 * التحقق من عناصر قائمتي الرأس والتذييل (docs/SPEC.md §9 menu_items، FR-54).
 *
 * كل عنصر: تسمية (نص حر بلا آيبان ولا رقم حساب) ووجهة واحدة فقط: صفحة موجودة
 * أو رابط مسموح (App\Support\SafeLink). وعدد العناصر ضمن security.pages.max_menu_items.
 */
class ValidateMenuItems
{
    public const int LABEL_MAX_LENGTH = 40;

    /**
     * @param  array<array-key, mixed>  $menus  العناصر مجمّعة حسب الموضع: ['header' => [...], 'footer' => [...]]
     * @return list<array{location: string, label: string, page_id: int|null, url: string|null}>
     *
     * @throws ValidationException
     */
    public function handle(array $menus): array
    {
        $maxItems = (int) config('security.pages.max_menu_items');
        $errors = [];
        $items = [];

        foreach (MenuLocation::cases() as $location) {
            $entries = is_array($menus[$location->value] ?? null) ? array_values($menus[$location->value]) : [];

            if (count($entries) > $maxItems) {
                $errors[$location->value][] = __('pages.validation.list_max', ['max' => $maxItems]);

                continue;
            }

            foreach ($entries as $index => $entry) {
                $path = "{$location->value}.{$index}";
                $entry = is_array($entry) ? $entry : [];

                $label = is_string($entry['label'] ?? null) ? trim($entry['label']) : '';
                $pageId = filter_var($entry['page_id'] ?? null, FILTER_VALIDATE_INT) ?: null;
                $url = is_string($entry['url'] ?? null) && trim($entry['url']) !== '' ? trim($entry['url']) : null;

                if ($label === '') {
                    $errors["{$path}.label"][] = __('pages.validation.required');
                } elseif (mb_strlen($label) > self::LABEL_MAX_LENGTH) {
                    $errors["{$path}.label"][] = __('pages.validation.max', ['max' => self::LABEL_MAX_LENGTH]);
                } elseif ($message = $this->bankDetailsMessage($label)) {
                    $errors["{$path}.label"][] = $message;
                }

                if ($pageId !== null) {
                    $url = null;

                    if (! Page::query()->whereKey($pageId)->exists()) {
                        $errors["{$path}.page_id"][] = __('pages.validation.invalid');
                    }
                } elseif ($url === null) {
                    $errors["{$path}.url"][] = __('pages.validation.menu_target');
                } elseif (! SafeLink::isAllowed($url)) {
                    $errors["{$path}.url"][] = __('pages.validation.link');
                } elseif ($message = $this->bankDetailsMessage($url)) {
                    $errors["{$path}.url"][] = $message;
                }

                $items[] = ['location' => $location->value, 'label' => $label, 'page_id' => $pageId, 'url' => $url];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $items;
    }

    /**
     * يحوّل عناصر نسخة محفوظة (قائمة مسطّحة) إلى الصيغة المجمّعة التي يقبلها handle().
     *
     * @param  list<array{location: string, label: string, page_id: int|null, url: string|null}>  $items
     * @return array<string, list<array{label: string, page_id: int|null, url: string|null}>>
     */
    public static function group(array $items): array
    {
        $grouped = array_fill_keys(array_map(fn (MenuLocation $location): string => $location->value, MenuLocation::cases()), []);

        foreach ($items as $item) {
            $grouped[$item['location']][] = ['label' => $item['label'], 'page_id' => $item['page_id'], 'url' => $item['url']];
        }

        return $grouped;
    }

    private function bankDetailsMessage(string $value): ?string
    {
        return match (true) {
            BankDetailsInText::containsIban($value) => (string) __('pages.validation.iban_in_text'),
            BankDetailsInText::containsAccountNumber($value) => (string) __('pages.validation.account_in_text'),
            default => null,
        };
    }
}
