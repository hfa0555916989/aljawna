<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * دعوات حساب المدير (php artisan admin:invite): لا واجهة ويب لإنشائها،
     * وتُنشأ عبر سطر الأوامر فقط بصلاحية الوصول إلى الخادم (docs/SPEC.md §2).
     * بلا عمود "دعاها" لأن الأمر يعمل خارج سياق مستخدم مسجَّل دخوله، وقد لا
     * يوجد مدير آخر عند أول تشغيل.
     */
    public function up(): void
    {
        Schema::create('admin_invites', function (Blueprint $table): void {
            $table->id();
            $table->string('phone');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->index('phone');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invites');
    }
};
