<?php

declare(strict_types=1);

namespace App\Actions\Content;

use App\Models\Page;
use App\Rules\NoBankDetailsInContent;
use App\Support\PageBlocks;
use App\Support\ReservedSlugs;
use Closure;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * التحقق من بيانات صفحة قبل حفظ مسودتها (docs/SPEC.md FR-50..56).
 *
 * العنوان والوصف والمسار نصوص حرة أيضًا فيُمنع فيها الآيبان ورقم الحساب. المسار
 * لاتيني صغير بشرطات، ولا يأخذ مسارًا محجوزًا أو مسار نظام، ومسار الصفحة النظامية ثابت.
 */
class ValidatePage
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{title: string, slug: string, seo_description: string|null, blocks: list<array{type: string, data: array<string, mixed>}>}
     *
     * @throws ValidationException
     */
    public function handle(array $data, ?Page $page = null): array
    {
        $input = [
            'title' => is_string($data['title'] ?? null) ? trim($data['title']) : ($data['title'] ?? null),
            'slug' => $page?->is_system ? $page->slug : (is_string($data['slug'] ?? null) ? strtolower(trim($data['slug'])) : ($data['slug'] ?? null)),
            'seo_description' => is_string($data['seo_description'] ?? null) ? (trim($data['seo_description']) ?: null) : ($data['seo_description'] ?? null),
        ];

        $validator = Validator::make($input, [
            'title' => ['required', 'string', 'max:150', new NoBankDetailsInContent],
            'slug' => $page?->is_system ? [] : [
                'required',
                'string',
                'max:'.ReservedSlugs::MAX_LENGTH,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (is_string($value) && ! ReservedSlugs::isValidFormat($value)) {
                        $fail(__('pages.validation.slug_format'));
                    } elseif (is_string($value) && ReservedSlugs::isReserved($value)) {
                        $fail(__('pages.validation.slug_reserved'));
                    }
                },
                Rule::unique('pages', 'slug')->ignore($page?->getKey()),
                new NoBankDetailsInContent,
            ],
            'seo_description' => ['nullable', 'string', 'max:300', new NoBankDetailsInContent],
        ], [], [
            'title' => __('pages.fields.title'),
            'slug' => __('pages.fields.slug'),
            'seo_description' => __('pages.fields.seo_description'),
        ]);

        $errors = $validator->fails() ? $validator->errors()->toArray() : [];
        $blocks = [];

        try {
            $blocks = PageBlocks::validate($data['blocks'] ?? []);
        } catch (ValidationException $exception) {
            $errors = [...$errors, ...$exception->errors()];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        /**
         * مسار الصفحة النظامية بلا قواعد فلا يرد في validated()، ويُؤخذ من الصفحة نفسها.
         *
         * @var array{title: string, slug?: string, seo_description?: string|null} $validated
         */
        $validated = $validator->validated();

        return [
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?? (string) $input['slug'],
            'seo_description' => $validated['seo_description'] ?? null,
            'blocks' => $blocks,
        ];
    }
}
