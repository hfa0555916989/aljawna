<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;
use App\PermissionKey;
use App\UserRole;

/**
 * الحوالات وإيصالاتها (docs/SPEC.md §2, §12.6, FR-13, FR-16).
 *
 * يرفع الحوالات حساب بدور المبادر وحده بلا استثناء، فلا يرفعها المدير ولا المشرف (قرار T07).
 * ويرى إيصاله صاحبه ومن يملك transfers.view فقط.
 * المدير الفعّال يملك ما سوى الرفع ضمنيًا عبر Gate::before، والمعطَّل ممنوع من كل شيء.
 * لا حذف للحوالات لأي أحد؛ لا يوجد في المواصفة مسار لإزالة حوالة (§14 بند مفتوح 5).
 */
class TransferPolicy implements DeniesAbilitiesToEveryone, ReservesAbilitiesToPolicy
{
    public function abilitiesDeniedToEveryone(): array
    {
        return ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny'];
    }

    public function abilitiesReservedToPolicy(): array
    {
        return ['create'];
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::User;
    }

    public function viewReceipt(User $user, Transfer $transfer): bool
    {
        return $transfer->isOwnedBy($user) || $user->can(PermissionKey::TransfersView->value);
    }

    public function delete(User $user, Transfer $transfer): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Transfer $transfer): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Transfer $transfer): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
