<?php

declare(strict_types=1);

namespace App\Models;

use App\MenuLocation;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * عنصر في قائمة الرأس أو التذييل (docs/SPEC.md §9 menu_items). يشير إلى صفحة
 * أو إلى رابط مسموح (App\Support\SafeLink)، ويُكتب عبر App\Actions\Content\SaveMenus فقط.
 *
 * @property MenuLocation $location
 */
#[Fillable(['location', 'label', 'page_id', 'url', 'position'])]
class MenuItem extends Model
{
    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'location' => MenuLocation::class,
            'position' => 'integer',
        ];
    }
}
