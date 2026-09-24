<?php

declare(strict_types=1);

namespace App;

/**
 * أنواع سجل كلمات المرور المستعادة (docs/SPEC.md §4.3). يُستكمل phone_changed في T12.
 */
enum RecoveryLogAction: string
{
    case LinkRegistered = 'link_registered';
    case LinkOther = 'link_other';
    case PhoneChanged = 'phone_changed';
}
