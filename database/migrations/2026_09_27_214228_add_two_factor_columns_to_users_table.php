<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التحقق بخطوتين (TOTP) لأدوار لوحة الإدارة عبر مزوّد Filament (AppAuthentication):
 * السر مشفَّر بمفتاح التطبيق، ورموز الاسترداد مجزّأة ثم مشفَّرة كقائمة (docs/DECISIONS.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('app_authentication_secret')->nullable();
            $table->text('app_authentication_recovery_codes')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['app_authentication_secret', 'app_authentication_recovery_codes']);
        });
    }
};
