<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Auth\ResetTwoFactor;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * طوارئ: إعادة ضبط التحقق بخطوتين لمستخدم فقد جهازه ورموز استرداده.
 * سطر الأوامر فقط، ولا واجهة ويب له.
 *
 *     php artisan admin:reset-2fa +9665XXXXXXXX
 */
class ResetTwoFactorCommand extends Command
{
    protected $signature = 'admin:reset-2fa
        {phone : رقم جوال المستخدم المسجّل}
        {--force : التنفيذ دون سؤال تأكيد}';

    protected $description = 'إعادة ضبط التحقق بخطوتين لمستخدم وإنهاء جلساته (طوارئ)';

    public function handle(ResetTwoFactor $resetTwoFactor): int
    {
        if (! $this->option('force') && ! $this->confirm(__('admin.two_factor_reset.confirm'))) {
            $this->warn(__('admin.two_factor_reset.cancelled'));

            return self::FAILURE;
        }

        try {
            $user = $resetTwoFactor->handle((string) $this->argument('phone'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info(__($user->hasPanelRole() ? 'admin.two_factor_reset.done_panel' : 'admin.two_factor_reset.done'));

        return self::SUCCESS;
    }
}
