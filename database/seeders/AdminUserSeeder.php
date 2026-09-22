<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Support\SaudiPhone;
use App\UserRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * ينشئ المدير الأول من إعدادات البيئة (ADMIN_NAME, ADMIN_PHONE, ADMIN_PASSWORD)
 * دون أي قيم ثابتة في الكود (tasks/T01-users-base.md). آمن للتشغيل أكثر من
 * مرة: لا يُنشئ مديرين مكررين لنفس رقم الجوال.
 */
class AdminUserSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $name = config('admin.name');
        $phone = SaudiPhone::normalize(config('admin.phone'));
        $password = config('admin.password');

        if (! is_string($name) || $name === '' || $phone === null || ! is_string($password) || $password === '') {
            Log::warning('AdminUserSeeder: تخطّي إنشاء المدير الأول لعدم اكتمال ADMIN_NAME/ADMIN_PHONE/ADMIN_PASSWORD في البيئة.');

            return;
        }

        User::query()->firstOrCreate(
            ['phone' => $phone],
            [
                'full_name' => $name,
                'password' => Hash::make($password),
                'role' => UserRole::Admin,
                'is_active' => true,
            ],
        );
    }
}
