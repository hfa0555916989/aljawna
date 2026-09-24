<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PasswordResetRequest;
use App\PasswordResetStatus;
use App\Services\Audit;
use Illuminate\Console\Command;

/**
 * يغلق طلبات الاستعادة التي مضى عليها 24 ساعة (docs/SPEC.md §4).
 */
class ExpirePasswordResetRequests extends Command
{
    protected $signature = 'recovery:expire';

    protected $description = 'إغلاق طلبات استعادة كلمة المرور المنتهية';

    public function handle(): int
    {
        PasswordResetRequest::query()
            ->whereIn('status', [
                PasswordResetStatus::Pending->value,
                PasswordResetStatus::Claimed->value,
                PasswordResetStatus::LinkSent->value,
            ])
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->each(function (PasswordResetRequest $request): void {
                $request->forceFill(['status' => PasswordResetStatus::Expired])->save();

                Audit::record('recovery.expired', $request, [], null);
            });

        return self::SUCCESS;
    }
}
