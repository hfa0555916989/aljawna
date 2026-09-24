<?php

declare(strict_types=1);

namespace App\Actions\Beneficiaries;

use App\Models\Beneficiary;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * اعتماد المستفيد بعد مراجعة بياناته، فيظهر للعامة (docs/SPEC.md FR-29، تدفق تسجيل مستفيد).
 * يُسجَّل في audit_logs بمن اعتمد.
 */
class ApproveBeneficiary
{
    public const AUDIT_ACTION = 'beneficiary.approved';

    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Beneficiary $beneficiary): Beneficiary
    {
        Gate::forUser($actor)->authorize('update', $beneficiary);

        if ($beneficiary->isApproved()) {
            return $beneficiary;
        }

        DB::transaction(function () use ($actor, $beneficiary): void {
            $beneficiary->forceFill([
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ])->save();

            Audit::record(self::AUDIT_ACTION, $beneficiary, [], $actor);
        });

        return $beneficiary;
    }
}
