<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * الكتل المسموحة في منشئ الصفحات وحقولها، وتطبيعها والتحقق منها على الخادم
 * (docs/SPEC.md FR-50..56, §12.13). هذا هو المرجع الوحيد لبنية الكتل: نموذج
 * اللوحة يعرض الحقول نفسها، والإجراءات لا تحفظ إلا ما يمرّ من validate().
 *
 * لا HTML حر ولا سكربت ولا iframe ولا CSS حر: النص المنسّق يُنظَّف بقائمة سماح
 * (App\Support\SafeHtml)، والروابط http/https/tel/wa.me أو مسار داخلي فقط، وأي
 * نص فيه آيبان أو رقم حساب يُرفض. كتلة "المبادرات" نظامية: تختار طريقة العرض
 * وعدد البطاقات فقط، وبياناتها تُقرأ من قاعدة المستفيدين بلا أي بيانات بنكية.
 */
final class PageBlocks
{
    public const string HERO = 'hero';

    public const string RICH_TEXT = 'rich_text';

    public const string IMAGE = 'image';

    public const string CARDS = 'cards';

    public const string STEPS = 'steps';

    public const string FAQ = 'faq';

    public const string COUNTERS = 'counters';

    public const string DIVIDER = 'divider';

    public const string INITIATIVES = 'initiatives';

    public const array TYPES = [
        self::HERO, self::RICH_TEXT, self::IMAGE, self::CARDS, self::STEPS,
        self::FAQ, self::COUNTERS, self::DIVIDER, self::INITIATIVES,
    ];

    public const array BUTTON_STYLES = ['primary', 'secondary'];

    public const array COUNTER_METRICS = ['available', 'closed', 'initiators', 'receipts', 'total'];

    public const string INITIATIVES_AVAILABLE = 'available';

    public const string INITIATIVES_LATEST = 'latest';

    public const array INITIATIVE_MODES = [self::INITIATIVES_AVAILABLE, self::INITIATIVES_LATEST];

    public const int MAX_INITIATIVE_CARDS = 12;

    public const int RICH_TEXT_MAX_LENGTH = 20000;

    /**
     * اسم الصورة كما يحفظه App\Services\BrandingImageStorage (40 محرفًا عشوائيًا وامتداد).
     */
    public const string IMAGE_PATH_PATTERN = '/^[A-Za-z0-9]{40}\.(png|jpg)$/';

    private function __construct()
    {
        //
    }

    /**
     * حقول كل كتلة. الأنواع: text (نص عادي)، rich (نص منسّق)، link، bool،
     * choice (خيار واحد، الافتراضي أولها)، choices (عدة خيارات)، int، image، list.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public static function schema(): array
    {
        $heading = ['kind' => 'text', 'max' => 150];

        return [
            self::HERO => [
                'title' => ['kind' => 'text', 'max' => 150, 'required' => true],
                'lead' => ['kind' => 'text', 'max' => 500],
                'show_counters' => ['kind' => 'bool'],
                'buttons' => ['kind' => 'list', 'max' => 2, 'fields' => [
                    'label' => ['kind' => 'text', 'max' => 40, 'required' => true],
                    'url' => ['kind' => 'link', 'required' => true],
                    'style' => ['kind' => 'choice', 'options' => self::BUTTON_STYLES],
                    'guests_only' => ['kind' => 'bool'],
                ]],
            ],
            self::RICH_TEXT => [
                'heading' => $heading,
                'body' => ['kind' => 'rich', 'max' => self::RICH_TEXT_MAX_LENGTH, 'required' => true],
            ],
            self::IMAGE => [
                'path' => ['kind' => 'image', 'required' => true],
                'alt' => ['kind' => 'text', 'max' => 150, 'required' => true],
                'caption' => ['kind' => 'text', 'max' => 200],
            ],
            self::CARDS => [
                'heading' => $heading,
                'items' => ['kind' => 'list', 'min' => 1, 'max' => 12, 'fields' => [
                    'title' => ['kind' => 'text', 'max' => 100, 'required' => true],
                    'body' => ['kind' => 'text', 'max' => 500],
                    'link_label' => ['kind' => 'text', 'max' => 40],
                    'link_url' => ['kind' => 'link'],
                ]],
            ],
            self::STEPS => [
                'heading' => $heading,
                'items' => ['kind' => 'list', 'min' => 1, 'max' => 10, 'fields' => [
                    'title' => ['kind' => 'text', 'max' => 100, 'required' => true],
                    'body' => ['kind' => 'text', 'max' => 500],
                ]],
            ],
            self::FAQ => [
                'heading' => $heading,
                'items' => ['kind' => 'list', 'min' => 1, 'max' => 20, 'fields' => [
                    'question' => ['kind' => 'text', 'max' => 200, 'required' => true],
                    'answer' => ['kind' => 'text', 'max' => 2000, 'required' => true],
                ]],
            ],
            self::COUNTERS => [
                'heading' => $heading,
                'metrics' => ['kind' => 'choices', 'options' => self::COUNTER_METRICS, 'required' => true],
            ],
            self::DIVIDER => [],
            self::INITIATIVES => [
                'heading' => $heading,
                'mode' => ['kind' => 'choice', 'options' => self::INITIATIVE_MODES],
                'limit' => ['kind' => 'int', 'min' => 1, 'max' => self::MAX_INITIATIVE_CARDS, 'default' => 3],
            ],
        ];
    }

    /**
     * يطبّع الكتل ويتحقق منها، ويعيدها بالحقول المعروفة فقط.
     *
     * @return list<array{type: string, data: array<string, mixed>}>
     *
     * @throws ValidationException
     */
    public static function validate(mixed $blocks, string $attribute = 'blocks'): array
    {
        $blocks = is_array($blocks) ? array_values($blocks) : [];
        $maxBlocks = (int) config('security.pages.max_blocks');

        if (count($blocks) > $maxBlocks) {
            throw ValidationException::withMessages([$attribute => __('pages.validation.too_many_blocks', ['max' => $maxBlocks])]);
        }

        $schema = self::schema();
        $errors = [];
        $normalized = [];

        foreach ($blocks as $index => $block) {
            $type = is_array($block) ? ($block['type'] ?? null) : null;

            if (! is_string($type) || ! isset($schema[$type])) {
                $errors["{$attribute}.{$index}"][] = __('pages.validation.unknown_block');

                continue;
            }

            $data = is_array($block['data'] ?? null) ? $block['data'] : [];

            $normalized[] = [
                'type' => $type,
                'data' => self::normalizeFields($schema[$type], $data, "{$attribute}.{$index}.data", $errors),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $maxKilobytes = (int) config('security.pages.max_kilobytes');

        if (strlen((string) json_encode($normalized, JSON_UNESCAPED_UNICODE)) > $maxKilobytes * 1024) {
            throw ValidationException::withMessages([$attribute => __('pages.validation.too_large', ['max' => $maxKilobytes])]);
        }

        return $normalized;
    }

    /**
     * @param  array<string, array<string, mixed>>  $fields
     * @param  array<array-key, mixed>  $data
     * @param  array<string, list<string>>  $errors
     * @return array<string, mixed>
     */
    private static function normalizeFields(array $fields, array $data, string $path, array &$errors): array
    {
        $normalized = [];

        foreach ($fields as $name => $spec) {
            $normalized[$name] = self::normalizeValue($spec, $data[$name] ?? null, "{$path}.{$name}", $errors);
        }

        return $normalized;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, list<string>>  $errors
     */
    private static function normalizeValue(array $spec, mixed $raw, string $path, array &$errors): mixed
    {
        $required = (bool) ($spec['required'] ?? false);

        return match ($spec['kind']) {
            'text' => self::normalizeText($raw, (int) $spec['max'], $required, $path, $errors),
            'rich' => self::normalizeRich($raw, (int) $spec['max'], $required, $path, $errors),
            'link' => self::normalizeLink($raw, $required, $path, $errors),
            'bool' => filter_var($raw, FILTER_VALIDATE_BOOLEAN),
            'choice' => self::normalizeChoice($raw, (array) $spec['options'], $path, $errors),
            'choices' => self::normalizeChoices($raw, (array) $spec['options'], $required, $path, $errors),
            'int' => self::normalizeInt($raw, (int) $spec['min'], (int) $spec['max'], (int) $spec['default'], $path, $errors),
            'image' => self::normalizeImage($raw, $required, $path, $errors),
            'list' => self::normalizeList($raw, $spec, $path, $errors),
            default => null,
        };
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function normalizeText(mixed $raw, int $max, bool $required, string $path, array &$errors): ?string
    {
        $value = self::stringOrNull($raw, $path, $errors);

        if ($value === null) {
            if ($required) {
                $errors[$path][] = __('pages.validation.required');
            }

            return null;
        }

        if (mb_strlen($value) > $max) {
            $errors[$path][] = __('pages.validation.max', ['max' => $max]);
        }

        self::rejectBankDetails($value, $path, $errors);

        return $value;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function normalizeRich(mixed $raw, int $max, bool $required, string $path, array &$errors): ?string
    {
        $value = self::stringOrNull($raw, $path, $errors);

        if ($value !== null) {
            self::rejectBankDetails($value, $path, $errors);
        }

        $clean = $value === null ? '' : SafeHtml::clean($value);

        if (SafeHtml::plainText($clean) === '') {
            if ($required) {
                $errors[$path][] = __('pages.validation.required');
            }

            return null;
        }

        if (mb_strlen($clean) > $max) {
            $errors[$path][] = __('pages.validation.max', ['max' => $max]);
        }

        return $clean;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function normalizeLink(mixed $raw, bool $required, string $path, array &$errors): ?string
    {
        $value = self::stringOrNull($raw, $path, $errors);

        if ($value === null) {
            if ($required) {
                $errors[$path][] = __('pages.validation.required');
            }

            return null;
        }

        if (! SafeLink::isAllowed($value)) {
            $errors[$path][] = __('pages.validation.link');
        }

        self::rejectBankDetails($value, $path, $errors);

        return $value;
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @param  array<string, list<string>>  $errors
     */
    private static function normalizeChoice(mixed $raw, array $options, string $path, array &$errors): mixed
    {
        if ($raw === null || $raw === '') {
            return $options[0];
        }

        if (! in_array($raw, $options, true)) {
            $errors[$path][] = __('pages.validation.invalid');

            return $options[0];
        }

        return $raw;
    }

    /**
     * @param  array<array-key, mixed>  $options
     * @param  array<string, list<string>>  $errors
     * @return list<mixed>
     */
    private static function normalizeChoices(mixed $raw, array $options, bool $required, string $path, array &$errors): array
    {
        $values = is_array($raw) ? array_values($raw) : [];

        if (array_diff($values, $options) !== []) {
            $errors[$path][] = __('pages.validation.invalid');
        }

        $selected = array_values(array_filter($options, fn (mixed $option): bool => in_array($option, $values, true)));

        if ($selected === [] && $required) {
            $errors[$path][] = __('pages.validation.required');
        }

        return $selected;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function normalizeInt(mixed $raw, int $min, int $max, int $default, string $path, array &$errors): int
    {
        if ($raw === null || $raw === '') {
            return $default;
        }

        $value = filter_var($raw, FILTER_VALIDATE_INT);

        if ($value === false || $value < $min || $value > $max) {
            $errors[$path][] = __('pages.validation.between', ['min' => $min, 'max' => $max]);

            return $default;
        }

        return $value;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function normalizeImage(mixed $raw, bool $required, string $path, array &$errors): ?string
    {
        if (is_array($raw)) {
            $raw = array_values($raw)[0] ?? null;
        }

        $value = self::stringOrNull($raw, $path, $errors);

        if ($value === null) {
            if ($required) {
                $errors[$path][] = __('pages.validation.required');
            }

            return null;
        }

        $disk = Storage::disk((string) config('security.branding.disk'));

        if (preg_match(self::IMAGE_PATH_PATTERN, $value) !== 1 || ! $disk->exists($value)) {
            $errors[$path][] = __('pages.validation.image');
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $spec
     * @param  array<string, list<string>>  $errors
     * @return list<array<string, mixed>>
     */
    private static function normalizeList(mixed $raw, array $spec, string $path, array &$errors): array
    {
        $items = is_array($raw) ? array_values($raw) : [];
        $min = (int) ($spec['min'] ?? 0);
        $max = (int) $spec['max'];

        if (count($items) > $max) {
            $errors[$path][] = __('pages.validation.list_max', ['max' => $max]);
            $items = array_slice($items, 0, $max);
        }

        if (count($items) < $min) {
            $errors[$path][] = __('pages.validation.list_min', ['min' => $min]);
        }

        /** @var array<string, array<string, mixed>> $fields */
        $fields = $spec['fields'];
        $normalized = [];

        foreach ($items as $index => $item) {
            $normalized[] = self::normalizeFields($fields, is_array($item) ? $item : [], "{$path}.{$index}", $errors);
        }

        return $normalized;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function stringOrNull(mixed $raw, string $path, array &$errors): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_int($raw) || is_float($raw)) {
            $raw = (string) $raw;
        }

        if (! is_string($raw)) {
            $errors[$path][] = __('pages.validation.invalid');

            return null;
        }

        $value = trim($raw);

        return $value === '' ? null : $value;
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    private static function rejectBankDetails(string $value, string $path, array &$errors): void
    {
        if (BankDetailsInText::containsIban($value)) {
            $errors[$path][] = __('pages.validation.iban_in_text');
        } elseif (BankDetailsInText::containsAccountNumber($value)) {
            $errors[$path][] = __('pages.validation.account_in_text');
        }
    }
}
