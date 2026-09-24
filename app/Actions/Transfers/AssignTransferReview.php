<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\Models\Transfer;
use App\Models\User;
use App\Services\Audit;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * إسناد المراجعة، أو إسناد المراجعة النهائية إلى المشرف نفسه بعد تعليقه (FR-44, FR-46, FR-48).
 */
class AssignTransferReview
{
    public function handle(User $actor, Transfer $transfer, User $assignee): void
    {
        if (! $actor->can('assign', [$transfer, $assignee])) {
            throw new AuthorizationException;
        }

        $final = $transfer->comments()->exists();

        $transfer->forceFill([
            'assigned_to' => $assignee->id,
            'assigned_by' => $actor->id,
            'assigned_at' => now(),
        ])->save();

        Audit::record($final ? 'transfer.final_assigned' : 'transfer.assigned', $transfer, [
            'assignee_id' => $assignee->id,
        ], $actor);
    }
}
