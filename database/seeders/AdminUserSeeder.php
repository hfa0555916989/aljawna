<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Auth\ResetTwoFactor;
use App\Models\User;
use App\Support\SaudiPhone;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * مدير للتطوير المحلي من إعدادات البيئة (ADMIN_NAME, ADMIN_PHONE, ADMIN_PASSWORD)
 * دون أي قيم ثابتة في الكود (tasks/T01-users-base.md). آمن للتشغيل أكثر من
 * مرة: لا يُنشئ مديرين مكررين لنفس رقم الجوال.
 *
 * لا يعمل في الإنتاج إطلاقًا: المدير الأول هناك يُنشأ عبر php artisan admin:invite
 * فقط، فيُعدّ التحقق بخطوتين قبل إنشاء حسابه (docs/DECISIONS.md، T20). ولأن كلمة
 * المرور وحدها لا تُدخل حسابًا إداريًا، يطبع رابط إعداد التحقق عند إنشائه.
 */
class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            Log::warning('AdminUserSeeder: تخطّي إنشاء المدير في الإنتاج. استخدم php artisan admin:invite.');
            $this->command?->warn('AdminUserSeeder: لا يعمل في الإنتاج. أنشئ المدير الأول عبر php artisan admin:invite.');

            return;
        }

        $name = config('admin.name');
        $phone = SaudiPhone::normalize(config('admin.phone'));
        $password = config('admin.password');

        if (! is_string($name) || $name === '' || $phone === null || ! is_string($password) || $password === '') {
            Log::warning('AdminUserSeeder: تخطّي إنشاء المدير الأول لعدم اكتمال ADMIN_NAME/ADMIN_PHONE/ADMIN_PASSWORD في البيئة.');

            return;
        }

        $admin = User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'full_name' => $name,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );

        if ($admin->wasRecentlyCreated && $this->command !== null) {
            $setup = app(ResetTwoFactor::class)->handle($phone);

            $this->command->info(__('admin.two_factor_reset.link_label'));
            $this->command->line((string) $setup['setup_url']);
        }
    }
}
