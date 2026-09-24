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
 * تسجيل مستفيد جديد (docs/SPEC.md FR-29). يبدأ متاحًا وغير معتمد، فلا يظهر للعامة حتى يُعتمد.
 * يُسجَّل في audit_logs بمن سجّله، دون بيانات الحساب البنكي.
 */
class CreateBeneficiary
{
    public const AUDIT_ACTION = 'beneficiary.created';

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, array $data): Beneficiary
    {
        Gate::forUser($actor)->authorize('create', Beneficiary::class);

        return DB::transaction(function () use ($actor, $data): Beneficiary {
            $beneficiary = new Beneficiary($data);
            $beneficiary->forceFill([
                'status' => BeneficiaryStatus::Active,
                'created_by' => $actor->getKey(),
                'approved_by' => null,
                'approved_at' => null,
            ])->save();

            Audit::record(self::AUDIT_ACTION, $beneficiary, [], $actor);

            return $beneficiary;
        });
    }
}
