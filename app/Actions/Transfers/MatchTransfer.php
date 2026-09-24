<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\Models\Transfer;
use App\Models\User;
use App\Services\Audit;
use App\TransferReviewState;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * مطابقة اختيارية. لا تغيّر احتساب الحوالة (docs/SPEC.md FR-43, FR-48).
 */
class MatchTransfer
{
    public function handle(User $actor, Transfer $transfer): void
    {
        if (! $actor->can('match', $transfer)) {
            throw new AuthorizationException;
        }

        $transfer->forceFill(['review_state' => TransferReviewState::Matched])->save();

        Audit::record('transfer.matched', $transfer, [], $actor);
    }
}
