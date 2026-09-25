<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PasswordResetRequest;
use App\Models\User;
use App\PermissionKey;
use App\UserRole;

/**
 * معالجة طلبات الاستعادة: الاستلام والإلغاء والإرسال وتعديل رقم الدخول (docs/SPEC.md §4, §12.5).
 *
 * المدير الفعّال يعالج كل الطلبات ضمنيًا عبر Gate::before.
 * المشرف يعالج طلبات المبادرين فقط؛ طلبات حسابات المشرفين والمدير محجوزة للمدير حصرًا،
 * كي لا يستولي مشرف على حساب إداري بإرسال رابطه إلى رقم آخر أو تغيير رقم دخوله.
 */
class PasswordResetRequestPolicy
{
    public function handle(User $user, PasswordResetRequest $request): bool
    {
        if (! $user->can(PermissionKey::RecoveryHandle->value)) {
            return false;
        }

        $owner = $request->user()->first(['id', 'role']);

        return $owner instanceof User && $owner->role === UserRole::User;
    }
}
