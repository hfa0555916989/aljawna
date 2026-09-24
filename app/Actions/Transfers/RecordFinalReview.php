<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\Models\Transfer;
use App\Models\User;
use App\Rules\NoBankAccountInText;
use App\Services\Audit;
use App\TransferReviewState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * المراجعة النهائية: الحالة final_reviewed والملاحظة الختامية في final_note (FR-47, FR-48).
 * لا عمود لقرار منفصل في المواصفة، فالقرار هو إتمام المراجعة النهائية.
 */
class RecordFinalReview
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Transfer $transfer, string $note): void
    {
        if (! $actor->can('finalReview', $transfer)) {
            throw new AuthorizationException;
        }

        Validator::make(
            ['final_note' => $note],
            ['final_note' => ['required', 'string', 'max:2000', new NoBankAccountInText]],
        )->validate();

        $transfer->forceFill([
            'review_state' => TransferReviewState::FinalReviewed,
            'final_reviewed_by' => $actor->id,
            'final_reviewed_at' => now(),
            'final_note' => $note,
        ])->save();

        Audit::record('transfer.final_reviewed', $transfer, [], $actor);
    }
}
