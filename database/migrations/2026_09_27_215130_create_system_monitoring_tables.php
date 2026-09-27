<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مراقبة تشغيل النظام (صفحة "صحة النظام" وتنبيهات البريد، docs/DECISIONS.md):
 * - system_heartbeats: آخر نبض لكل مكوّن (المجدول، عامل الطوابير، النسخ الاحتياطي).
 * - system_alerts: آخر إرسال لكل نوع تنبيه، لمنع تكراره خلال مهلة التهدئة.
 * في قاعدة البيانات لا في Redis، فتبقى المراقبة صالحة حين يتعطل Redis نفسه.
 * لا بيانات شخصية في أي عمود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_heartbeats', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->unique();
            $table->timestamp('last_seen_at');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('system_alerts', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->timestamp('last_sent_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_alerts');
        Schema::dropIfExists('system_heartbeats');
    }
};
