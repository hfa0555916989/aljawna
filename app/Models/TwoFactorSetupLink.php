<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * رابط إعداد التحقق بخطوتين، يُصدر من php artisan admin:reset-2fa فقط.
 * الرمز الخام لا يُخزَّن، ولمرة واحدة، ويُبطل الإصدارُ الجديد ما قبله.
 *
 * @property int $id
 * @property int $user_id
 * @property Carbon $expires_at
 * @property Carbon|null $used_at
 */
#[Fillable(['user_id', 'token_hash', 'expires_at', 'used_at'])]
class TwoFactorSetupLink extends Model
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

    public static function findByToken(string $plainToken): ?self
    {
        return static::query()->where('token_hash', hash('sha256', $plainToken))->first();
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
