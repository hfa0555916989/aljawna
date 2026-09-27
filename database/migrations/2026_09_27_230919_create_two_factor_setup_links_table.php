<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * روابط إعداد التحقق بخطوتين من سطر الأوامر فقط (php artisan admin:reset-2fa):
 * كلمة المرور وحدها لا تكفي لدخول حساب إداري بلا تحقق، فيُعدّ صاحبه التحقق من
 * هذا الرابط أولًا (docs/DECISIONS.md، T20). الرمز الخام لا يُخزَّن.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('two_factor_setup_links', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('two_factor_setup_links');
    }
};
