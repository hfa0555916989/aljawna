<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * روابط تعيين كلمة مرور المدير من سطر الأوامر فقط (php artisan admin:reset-link).
 * منفصلة عن طلبات الاستعادة عبر المشرفين: لا طلب ولا مُصدِر من المستخدمين، ولا
 * تدخل في سجل الاستعادة أو إحصائياته. الرمز الخام لا يُخزَّن.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_password_resets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_resets');
    }
};
