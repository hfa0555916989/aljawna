<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\IssueAdminResetLink;
use App\Support\TwoFactorPolicy;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * طوارئ: رابط تعيين كلمة مرور لمدير موجود. سطر الأوامر فقط، ولا واجهة ويب له.
 * متى كان TWO_FACTOR_REQUIRED=false يشمل المشرف أيضًا، وصلاحيته 48 ساعة،
 * ويطلب تأكيد رقم الجوال (App\Actions\Admin\IssueAdminResetLink).
 *
 *     php artisan admin:reset-link +9665XXXXXXXX
 *     php artisan admin:reset-password +9665XXXXXXXX
 */
class ResetAdminLinkCommand extends Command
{
    protected $signature = 'admin:reset-link {phone : رقم جوال المدير أو المشرف المسجّل}';

    protected $description = 'توليد رابط لمرة واحدة لتعيين كلمة مرور مدير موجود، أو مشرف متى كان التحقق بخطوتين غير إلزامي';

    /**
     * @var list<string>
     */
    protected $aliases = ['admin:reset-password'];

    public function handle(IssueAdminResetLink $issueAdminResetLink): int
    {
        try {
            $result = $issueAdminResetLink->handle((string) $this->argument('phone'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info(TwoFactorPolicy::isRequired()
            ? __('admin.reset.issued', ['minutes' => IssueAdminResetLink::lifetimeMinutes()])
            : __('admin.reset.issued_hours', ['hours' => TwoFactorPolicy::simpleLinkHours()]));
        $this->line('');
        $this->line(__('admin.reset.link_label'));
        $this->line($result['reset_url']);
        $this->line('');
        $this->line(__('admin.reset.whatsapp_label'));
        $this->line($result['whatsapp_url']);

        return self::SUCCESS;
    }
}
