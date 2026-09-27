<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\IssueAdminResetLink;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * طوارئ: رابط تعيين كلمة مرور لمدير موجود. سطر الأوامر فقط، ولا واجهة ويب له.
 *
 *     php artisan admin:reset-link +9665XXXXXXXX
 */
class ResetAdminLinkCommand extends Command
{
    protected $signature = 'admin:reset-link {phone : رقم جوال المدير المسجّل}';

    protected $description = 'توليد رابط لمرة واحدة لتعيين كلمة مرور مدير موجود (طوارئ)';

    public function handle(IssueAdminResetLink $issueAdminResetLink): int
    {
        try {
            $result = $issueAdminResetLink->handle((string) $this->argument('phone'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info(__('admin.reset.issued', ['minutes' => (int) config('security.recovery.link_minutes')]));
        $this->line('');
        $this->line(__('admin.reset.link_label'));
        $this->line($result['reset_url']);
        $this->line('');
        $this->line(__('admin.reset.whatsapp_label'));
        $this->line($result['whatsapp_url']);

        return self::SUCCESS;
    }
}
