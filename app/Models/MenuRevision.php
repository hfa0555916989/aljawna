<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * نسخة من القائمتين معًا بعد كل حفظ (قرار المالك في docs/DECISIONS.md). للإضافة فقط.
 *
 * @property list<array{location: string, label: string, page_id: int|null, url: string|null}> $items
 * @property bool $is_baseline
 * @property Carbon $created_at
 */
#[Fillable(['items', 'author_id', 'is_baseline'])]
class MenuRevision extends Model
{
    public const UPDATED_AT = null;

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
            'items' => 'array',
            'is_baseline' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
