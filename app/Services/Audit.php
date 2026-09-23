<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * كتابة إجراء حساس في سجل التدقيق (docs/SPEC.md §12.7, FR-23).
 *
 * لا تضع في meta أرقام جوال أو آيبان أو محتوى إيصالات (.cursor/rules/30-security-privacy).
 */
class Audit
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function record(string $action, ?Model $subject = null, array $meta = [], ?User $actor = null): AuditLog
    {
        $actor ??= auth()->user();

        return AuditLog::query()->create([
            'actor_id' => $actor instanceof User ? $actor->getKey() : null,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'meta' => $meta,
            'ip' => request()->ip(),
        ]);
    }
}
