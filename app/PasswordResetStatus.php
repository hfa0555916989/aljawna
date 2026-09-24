<?php

declare(strict_types=1);

namespace App;

/**
 * حالات طلب استعادة كلمة المرور (docs/SPEC.md §4).
 */
enum PasswordResetStatus: string
{
    case Pending = 'pending';
    case Claimed = 'claimed';
    case LinkSent = 'link_sent';
    case Completed = 'completed';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Pending, self::Claimed, self::LinkSent], true);
    }
}
