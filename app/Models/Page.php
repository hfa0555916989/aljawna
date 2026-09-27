<?php

declare(strict_types=1);

namespace App\Models;

use App\PageStatus;
use App\Services\SiteMenu;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * صفحة من منشئ الصفحات (docs/SPEC.md §9 pages، FR-50..56).
 *
 * blocks هي المسودة الحالية التي يحرّرها المشرف وتظهر في المعاينة فقط. ما يراه
 * الزوار هو كتل آخر نسخة في page_revisions لصفحة منشورة؛ النشر وحده يكتب نسخة.
 * الصفحة النظامية (is_system) هي الرئيسية: مسارها ثابت ولا يُلغى نشرها. لا حذف لأي صفحة.
 *
 * @property list<array{type: string, data: array<string, mixed>}> $blocks
 * @property PageStatus $status
 * @property bool $is_system
 * @property Carbon|null $published_at
 */
#[Fillable(['slug', 'title', 'seo_description', 'blocks'])]
class Page extends Model
{
    /** @use HasFactory<PageFactory> */
    use HasFactory;

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', PageStatus::Published);
    }

    /**
     * @return HasMany<PageRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class);
    }

    /**
     * @return HasOne<PageRevision, $this>
     */
    public function latestRevision(): HasOne
    {
        return $this->hasOne(PageRevision::class)->latestOfMany();
    }

    /**
     * @return HasOne<PageRevision, $this>
     */
    public function baselineRevision(): HasOne
    {
        return $this->hasOne(PageRevision::class)->where('is_baseline', true);
    }

    public function isPublished(): bool
    {
        return $this->status === PageStatus::Published;
    }

    /**
     * الكتل المنشورة للزوار: كتل آخر نسخة لصفحة منشورة، أو لا شيء.
     *
     * @return list<array{type: string, data: array<string, mixed>}>|null
     */
    public function liveBlocks(): ?array
    {
        if (! $this->isPublished()) {
            return null;
        }

        return $this->latestRevision?->blocks;
    }

    public function publicUrl(): string
    {
        return $this->is_system ? route('home') : route('pages.show', $this->slug);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'status' => PageStatus::class,
            'is_system' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * روابط القوائم تُبنى من مسارات الصفحات وحالة نشرها، فتُبطل عند أي تعديل.
     */
    protected static function booted(): void
    {
        static::saved(function (): void {
            SiteMenu::forget();
        });
    }
}
