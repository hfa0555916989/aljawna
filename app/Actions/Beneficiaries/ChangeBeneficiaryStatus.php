<?php

declare(strict_types=1);

namespace App\Actions\Beneficiaries;

use App\BeneficiaryStatus;
use App\Models\Beneficiary;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * إغلاق المبادرة أو إعادة فتحها (docs/SPEC.md FR-32). المغلقة توقف استقبال حوالات جديدة.
 * يُسجَّل في audit_logs بمن نفّذ.
 */
class ChangeBeneficiaryStatus
{
    public const AUDIT_CLOSED = 'beneficiary.closed';

    public const AUDIT_REOPENED = 'beneficiary.reopened';

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Beneficiary $beneficiary, BeneficiaryStatus $status): Beneficiary
    {
        Gate::forUser($actor)->authorize('update', $beneficiary);

        if ($beneficiary->status === $status) {
            return $beneficiary;
        }

        DB::transaction(function () use ($actor, $beneficiary, $status): void {
            $beneficiary->forceFill(['status' => $status])->save();

            Audit::record(
                $status === BeneficiaryStatus::Closed ? self::AUDIT_CLOSED : self::AUDIT_REOPENED,
                $beneficiary,
                [],
                $actor,
            );
        });

        return $beneficiary;
    }
}
