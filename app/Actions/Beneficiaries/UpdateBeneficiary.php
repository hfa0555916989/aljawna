<?php

declare(strict_types=1);

namespace App\Actions\Beneficiaries;

use App\Exceptions\BankAccountChangeNotConfirmed;
use App\Models\Beneficiary;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * تعديل بيانات مستفيد (docs/SPEC.md FR-33, §12.10). تغيير أي حقل بنكي لا يتم
 * دون تأكيد صريح، ويُسجَّل في audit_logs بمن غيّر والقيمة القديمة والجديدة،
 * ويُلغي اعتماد المستفيد فيختفي من القوائم العامة حتى يُعتمد من جديد.
 */
class UpdateBeneficiary
{
    public const AUDIT_ACTION = 'beneficiary.bank_account_updated';

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws AuthorizationException
     * @throws BankAccountChangeNotConfirmed
     */
    public function handle(User $actor, Beneficiary $beneficiary, array $data, bool $bankChangeConfirmed = false): Beneficiary
    {
        Gate::forUser($actor)->authorize('update', $beneficiary);

        $changes = self::pendingBankChanges($beneficiary, $data);

        if ($changes !== [] && ! $bankChangeConfirmed) {
            throw new BankAccountChangeNotConfirmed(array_keys($changes));
        }

        DB::transaction(function () use ($actor, $beneficiary, $data, $changes): void {
            $beneficiary->fill($data);

            $revokesApproval = $changes !== [] && $beneficiary->isApproved();

            if ($revokesApproval) {
                $beneficiary->forceFill(['approved_by' => null, 'approved_at' => null]);
            }

            $beneficiary->save();

            if ($changes !== []) {
                Audit::record(self::AUDIT_ACTION, $beneficiary, [
                    'changes' => $changes,
                    'approval_revoked' => $revokesApproval,
                ], $actor);
            }
        });

        return $beneficiary;
    }

    /**
     * الحقول البنكية التي ستتغير لو حُفظت هذه البيانات، دون حفظها.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, array{old: string, new: string}>
     */
    public static function pendingBankChanges(Beneficiary $beneficiary, array $data): array
    {
        $candidate = (clone $beneficiary)->fill($data);
        $changes = [];

        foreach (Beneficiary::BANK_FIELDS as $field) {
            if ($candidate->isDirty($field)) {
                $changes[$field] = [
                    'old' => (string) $candidate->getOriginal($field),
                    'new' => (string) $candidate->getAttribute($field),
                ];
            }
        }

        return $changes;
    }
}
