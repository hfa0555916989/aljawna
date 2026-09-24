<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * رمز تعيين كلمة المرور. الخام لا يُخزَّن (docs/SPEC.md §9).
 *
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
#[Fillable(['request_id', 'issued_by', 'token_hash', 'sent_to_phone', 'is_other_number', 'reason', 'expires_at', 'used_at'])]
class PasswordResetToken extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<PasswordResetRequest, $this>
     */
    public function request(): BelongsTo
    {
        return $this->belongsTo(PasswordResetRequest::class, 'request_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_other_number' => 'boolean',
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
