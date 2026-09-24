<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * دعوة مشرف لمرة واحدة (docs/SPEC.md §6, §9). الرمز الخام لا يُخزَّن.
 *
 * @property list<string> $permissions
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 */
#[Fillable(['phone', 'token_hash', 'permissions', 'invited_by', 'expires_at', 'accepted_at'])]
class SupervisorInvite extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

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
            'permissions' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }
}
