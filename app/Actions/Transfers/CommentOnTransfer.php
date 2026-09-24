<?php

declare(strict_types=1);

namespace App\Actions\Transfers;

use App\Models\Transfer;
use App\Models\TransferComment;
use App\Models\User;
use App\Rules\NoBankAccountInText;
use App\Services\Audit;
use App\TransferReviewState;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * تعليق المسند إليه على حوالة متكررة. يُحفظ ولا يُعدَّل (FR-45, FR-48).
 */
class CommentOnTransfer
{
    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Transfer $transfer, string $body): TransferComment
    {
        if (! $actor->can('comment', $transfer)) {
            throw new AuthorizationException;
        }

        Validator::make(
            ['body' => $body],
            ['body' => ['required', 'string', 'max:2000', new NoBankAccountInText]],
        )->validate();

        $comment = $transfer->comments()->create([
            'author_id' => $actor->id,
            'body' => $body,
        ]);

        $transfer->forceFill(['review_state' => TransferReviewState::Commented])->save();

        Audit::record('transfer.commented', $transfer, [
            'comment_id' => $comment->id,
        ], $actor);

        return $comment;
    }
}
