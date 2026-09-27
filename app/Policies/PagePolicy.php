<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;
use App\PermissionKey;

/**
 * منشئ الصفحات بصلاحية content.manage فقط (docs/SPEC.md §2، FR-50..56).
 * المدير يملكها ضمنيًا عبر Gate::before، والمعطَّل ممنوع من كل شيء.
 *
 * لا حذف لأي صفحة ولو كان مديرًا (نسخها وعناصر القوائم تشير إليها، و"لا يُحذف
 * شيء" من السجل)، والصفحة النظامية (الرئيسية) لا يُلغى نشرها ولو كان مديرًا.
 */
class PagePolicy implements DeniesAbilitiesToEveryone, ReservesAbilitiesToPolicy
{
    public function abilitiesDeniedToEveryone(): array
    {
        return ['delete', 'deleteAny', 'forceDelete', 'forceDeleteAny', 'restore', 'restoreAny'];
    }

    public function abilitiesReservedToPolicy(): array
    {
        return ['unpublish'];
    }

    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Page $page): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Page $page): bool
    {
        return $this->canManage($user);
    }

    public function publish(User $user, Page $page): bool
    {
        return $this->canManage($user);
    }

    public function unpublish(User $user, Page $page): bool
    {
        return $this->canManage($user) && ! $page->is_system && $page->isPublished();
    }

    public function restoreRevision(User $user, Page $page): bool
    {
        return $this->canManage($user);
    }

    public function restoreBaseDesign(User $user): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Page $page): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function forceDelete(User $user, Page $page): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Page $page): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->can(PermissionKey::ContentManage->value);
    }
}
