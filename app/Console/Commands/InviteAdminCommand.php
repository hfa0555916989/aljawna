<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Admin\InviteAdmin;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

/**
 * دعوة حساب مدير جديد برقم جواله (docs/SPEC.md §2). لا واجهة ويب لهذا
 * الإجراء؛ يتوفّر عبر سطر الأوامر فقط لمن يملك وصولًا إلى الخادم.
 *
 *     php artisan admin:invite +9665XXXXXXXX
 */
class InviteAdminCommand extends Command
{
    protected $signature = 'admin:invite {phone : رقم جوال سعودي للمدير المدعو}';

    protected $description = 'توليد رابط دعوة مدير لمرة واحدة، صالح 48 ساعة';

    public function handle(InviteAdmin $inviteAdmin): int
    {
        try {
            $result = $inviteAdmin->handle((string) $this->argument('phone'));
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }

        $this->info('تم إنشاء دعوة المدير. صالحة 48 ساعة ولمرة واحدة.');
        $this->line('');
        $this->line('رابط الدعوة:');
        $this->line($result['join_url']);
        $this->line('');
        $this->line('رابط واتساب جاهز:');
        $this->line($result['whatsapp_url']);

        return self::SUCCESS;
    }
}
