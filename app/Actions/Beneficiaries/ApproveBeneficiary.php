<?php

declare(strict_types=1);

namespace App\Actions\Beneficiaries;

use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * اعتماد المستفيد بعد مراجعة بياناته، فيظهر للعامة (docs/SPEC.md FR-29، تدفق تسجيل مستفيد).
 */
class ApproveBeneficiary
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Beneficiary $beneficiary): Beneficiary
    {
        Gate::forUser($actor)->authorize('update', $beneficiary);

        if ($beneficiary->isApproved()) {
            return $beneficiary;
        }

        $beneficiary->forceFill([
            'approved_by' => $actor->getKey(),
            'approved_at' => now(),
        ])->save();

        return $beneficiary;
    }
}
