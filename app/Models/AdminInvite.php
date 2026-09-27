<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * دعوة مدير لمرة واحدة، تُنشأ عبر php artisan admin:invite فقط (docs/SPEC.md §2).
 * الرمز الخام لا يُخزَّن، ولا واجهة ويب لإنشاء هذا النوع من الدعوات.
 *
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
#[Fillable(['phone', 'token_hash', 'expires_at', 'accepted_at'])]
class AdminInvite extends Model
{
    public function isUsable(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
