<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SystemAlerts;
use Illuminate\Console\Command;
use Throwable;

/**
 * يرسل تنبيهًا تجريبيًا فورًا إلى ALERT_EMAIL للتحقق من إعداد البريد (Resend أو غيره)
 * بعد النشر أو بعد تغيير الدومين (docs/POST-DEPLOY-CHECKLIST.md).
 */
class SendTestAlertCommand extends Command
{
    protected $signature = 'alerts:test';

    protected $description = 'إرسال تنبيه تجريبي إلى ALERT_EMAIL';

    public function handle(SystemAlerts $alerts): int
    {
        if ((string) config('monitoring.alert_email') === '') {
            $this->error('ALERT_EMAIL فارغ: اضبطه في متغيرات البيئة ثم أعد النشر.');

            return self::FAILURE;
        }

        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->warn('MAIL_MAILER='.config('mail.default').': لن تصل الرسالة إلى بريد حقيقي.');
        }

        try {
            $recipient = $alerts->sendTest();
        } catch (Throwable $exception) {
            $this->error('فشل الإرسال ('.$exception::class.'): '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info('أُرسل التنبيه التجريبي إلى '.$recipient.'.');

        return self::SUCCESS;
    }
}
