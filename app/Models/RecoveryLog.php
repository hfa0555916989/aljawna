<?php

declare(strict_types=1);

namespace App\Models;

use App\RecoveryLogAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * سجل كلمات المرور المستعادة. للإضافة فقط (docs/SPEC.md §4.3). صفحة العرض في T12.
 */
#[Fillable(['request_id', 'user_id', 'performed_by', 'action', 'old_phone', 'new_phone', 'sent_to_phone', 'reason'])]
class RecoveryLog extends Model
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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('سجل الاستعادة لا يُعدَّل.');
        });

        static::deleting(function (): void {
            throw new LogicException('سجل الاستعادة لا يُحذف.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => RecoveryLogAction::class,
        ];
    }
}
