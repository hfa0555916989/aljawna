<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * تعليق مشرف على حوالة متكررة (docs/SPEC.md §9 transfer_comments). يُحفظ ولا يُعدَّل ولا يُحذف.
 *
 * @property int $transfer_id
 * @property int $author_id
 * @property string $body
 */
#[Fillable(['transfer_id', 'author_id', 'body'])]
class TransferComment extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return BelongsTo<Transfer, $this>
     */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new LogicException('تعليقات الحوالات لا تُعدَّل.');
        });

        static::deleting(function (): void {
            throw new LogicException('تعليقات الحوالات لا تُحذف.');
        });
    }
}
