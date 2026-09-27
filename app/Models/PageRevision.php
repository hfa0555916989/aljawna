<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * نسخة من كتل صفحة (docs/SPEC.md §9 page_revisions). للإضافة فقط: قاعدة البيانات
 * ترفض أي تعديل أو حذف، ومنها "نسخة الأساس" (is_baseline) للتصميم الأساسي.
 *
 * @property list<array{type: string, data: array<string, mixed>}> $blocks
 * @property bool $is_baseline
 * @property Carbon $created_at
 */
#[Fillable(['blocks', 'author_id', 'is_baseline'])]
class PageRevision extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_baseline' => false,
    ];

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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
            'is_baseline' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
