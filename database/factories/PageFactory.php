<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use App\PageStatus;
use App\Support\PageBlocks;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * صفحات وهمية. الافتراضي: مسودة غير منشورة بكتلة عنوان رئيسي واحدة.
 *
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => 'page-'.fake()->unique()->numberBetween(1000, 999999),
            'title' => 'صفحة تعريفية',
            'seo_description' => 'وصف قصير للصفحة.',
            'blocks' => [
                ['type' => PageBlocks::HERO, 'data' => ['title' => 'عنوان الصفحة', 'lead' => null, 'show_counters' => false, 'buttons' => []]],
            ],
            'status' => PageStatus::Draft,
            'is_system' => false,
        ];
    }

    /**
     * منشورة: حالة منشورة مع نسخة منشورة تطابق المسودة الحالية.
     */
    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => PageStatus::Published,
            'published_at' => now(),
        ])->afterCreating(function (Page $page): void {
            $page->revisions()->create(['blocks' => $page->blocks]);
        });
    }
}
