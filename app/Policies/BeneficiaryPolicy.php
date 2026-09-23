<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Beneficiary;
use App\Models\User;
use App\PermissionKey;

/**
 * إدارة المستفيدين بصلاحية beneficiaries.manage فقط (docs/SPEC.md §2, §12.10).
 * المدير الفعّال يملكها ضمنيًا عبر Gate::before، والمعطَّل ممنوع من كل شيء.
 * لا حذف للمستفيدين؛ الإيقاف يكون بالإغلاق (FR-32).
 */
class BeneficiaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->canManage($user);
    }

    public function view(User $user, Beneficiary $beneficiary): bool
    {
        return $this->canManage($user);
    }

    public function create(User $user): bool
    {
        return $this->canManage($user);
    }

    public function update(User $user, Beneficiary $beneficiary): bool
    {
        return $this->canManage($user);
    }

    public function delete(User $user, Beneficiary $beneficiary): bool
    {
        return false;
    }

    private function canManage(User $user): bool
    {
        return $user->can(PermissionKey::BeneficiariesManage->value);
    }
}
