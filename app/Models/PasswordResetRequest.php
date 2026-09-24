<?php

declare(strict_types=1);

namespace App\Models;

use App\PasswordResetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property PasswordResetStatus $status
 * @property Carbon|null $claimed_until
 * @property Carbon $expires_at
 */
#[Fillable(['user_id', 'status', 'claimed_by', 'claimed_until', 'requested_ip', 'expires_at'])]
class PasswordResetRequest extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function claimer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by');
    }

    /**
     * @return HasMany<PasswordResetToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(PasswordResetToken::class, 'request_id');
    }

    public function isClaimedBy(User $supervisor): bool
    {
        return $this->status === PasswordResetStatus::Claimed
            && (int) $this->claimed_by === (int) $supervisor->id
            && $this->claimed_until !== null
            && $this->claimed_until->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PasswordResetStatus::class,
            'claimed_until' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
