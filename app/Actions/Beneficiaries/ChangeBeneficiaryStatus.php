<?php

declare(strict_types=1);

namespace App\Actions\Beneficiaries;

use App\BeneficiaryStatus;
use App\Models\Beneficiary;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * إغلاق المبادرة أو إعادة فتحها (docs/SPEC.md FR-32). المغلقة توقف استقبال حوالات جديدة.
 */
class ChangeBeneficiaryStatus
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Beneficiary $beneficiary, BeneficiaryStatus $status): Beneficiary
    {
        Gate::forUser($actor)->authorize('update', $beneficiary);

        $beneficiary->forceFill(['status' => $status])->save();

        return $beneficiary;
    }
}
