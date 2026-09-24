<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Transfer;
use App\Models\User;
use App\PermissionKey;
use App\TransferReviewState;
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
        return ['create', 'match', 'comment', 'assign', 'finalReview'];
    }

    /**
     * عرض قائمة الحوالات في اللوحة (transfers.view). المدير يمرّ من Gate::before.
     */
    public function viewAny(User $user): bool
    {
        return $this->holds($user, PermissionKey::TransfersView);
    }

    public function view(User $user, Transfer $transfer): bool
    {
        return $this->viewAny($user);
    }

    /**
     * مطابقة اختيارية. المسند إليه يعلّق فقط ولا يطابق (FR-45).
     */
    public function match(User $user, Transfer $transfer): bool
    {
        if ($transfer->review_state === TransferReviewState::FinalReviewed) {
            return false;
        }

        if ($this->isAssignee($user, $transfer)) {
            return false;
        }

        return $this->holds($user, PermissionKey::TransfersReview);
    }

    /**
     * إسناد المراجعة، وبعد التعليق إسناد المراجعة النهائية إلى المشرف نفسه (FR-44, FR-46).
     */
    public function assign(User $user, Transfer $transfer, User $assignee): bool
    {
        if ($transfer->review_state === TransferReviewState::FinalReviewed || $transfer->wasFinalAssigned()) {
            return false;
        }

        if (! $this->holds($user, PermissionKey::TransfersAssign) || ! $this->isEligibleReviewer($assignee)) {
            return false;
        }

        if ($transfer->comments()->exists()) {
            return $user->id === $transfer->assigned_by && $assignee->id === $transfer->assigned_to;
        }

        return true;
    }

    /**
     * تعليق المسند إليه على الحوالة المتكررة فقط (FR-45).
     */
    public function comment(User $user, Transfer $transfer): bool
    {
        return $this->isAssignee($user, $transfer)
            && $transfer->is_repeated
            && ! $transfer->wasFinalAssigned()
            && $transfer->review_state !== TransferReviewState::FinalReviewed
            && $this->holds($user, PermissionKey::TransfersReview);
    }

    /**
     * المراجعة النهائية بعد إسنادها إلى المشرف نفسه (FR-47).
     */
    public function finalReview(User $user, Transfer $transfer): bool
    {
        return $this->isAssignee($user, $transfer)
            && $transfer->wasFinalAssigned()
            && $transfer->review_state !== TransferReviewState::FinalReviewed
            && $this->holds($user, PermissionKey::TransfersReview);
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

    /**
     * مشرف فعّال مُنح transfers.review مباشرة. المشرفون يُنشَؤون من المدير (T04)، ولا عمود دعوة بعد.
     */
    private function isEligibleReviewer(User $user): bool
    {
        return $user->is_active
            && $user->role === UserRole::Supervisor
            && $user->hasGrantedPermission(PermissionKey::TransfersReview->value);
    }

    private function isAssignee(User $user, Transfer $transfer): bool
    {
        return $user->id === $transfer->assigned_to;
    }

    private function holds(User $user, PermissionKey $permission): bool
    {
        if (! $user->is_active) {
            return false;
        }

        if ($user->role === UserRole::Admin) {
            return true;
        }

        return $user->role === UserRole::Supervisor
            && $user->hasGrantedPermission($permission->value);
    }
}
