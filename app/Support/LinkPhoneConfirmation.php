<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\Audit;
use Illuminate\Database\Eloquent\Model;

/**
 * تأكيد رقم الجوال على رابط دعوة أو استعادة في الوضع المبسّط (TwoFactorPolicy).
 *
 * الرقم الخاطئ يُحتسب في عمود failed_attempts للرابط نفسه، وعند بلوغ الحد يُلغى
 * الرابط بإنهاء صلاحيته ويُسجَّل ذلك في audit_logs. يُستدعى خارج أي معاملة تُلغى
 * بعده، كي يبقى العدّ محفوظًا حتى حين يُرفض الطلب.
 */
final class LinkPhoneConfirmation
{
    public const string REVOKED_AUDIT_ACTION = 'link.revoked_after_phone_attempts';

    private function __construct()
    {
        //
    }

    /**
     * @param  Model  $link  رابط بعمودي failed_attempts وexpires_at
     */
    public static function confirm(Model $link, string $expectedPhone, string $phoneInput): bool
    {
        $phone = SaudiPhone::normalize($phoneInput);

        if ($phone !== null && hash_equals($expectedPhone, $phone)) {
            return true;
        }

        $link->newQuery()->whereKey($link->getKey())->increment('failed_attempts');

        $revoked = $link->newQuery()
            ->whereKey($link->getKey())
            ->where('failed_attempts', '>=', TwoFactorPolicy::maxPhoneAttempts())
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        if ($revoked > 0) {
            Audit::record(self::REVOKED_AUDIT_ACTION, $link, ['link' => $link->getTable()], null);
        }

        return false;
    }
}
