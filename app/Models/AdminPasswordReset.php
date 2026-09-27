<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * رابط تعيين كلمة مرور مدير، يُصدر من php artisan admin:reset-link فقط.
 * الرمز الخام لا يُخزَّن، ولمرة واحدة، ويُبطل الإصدارُ الجديد ما قبله.
 *
 * @property int $user_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
#[Fillable(['user_id', 'token_hash', 'expires_at', 'used_at'])]
class AdminPasswordReset extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }
}
