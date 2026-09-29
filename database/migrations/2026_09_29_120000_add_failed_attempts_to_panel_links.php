<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * محاولات تأكيد رقم جوال خاطئة على روابط الدعوة والاستعادة في الوضع المبسّط
     * (TWO_FACTOR_REQUIRED=false)، ويُلغى الرابط عند بلوغ الحد (App\Support\LinkPhoneConfirmation).
     */
    public function up(): void
    {
        foreach (['admin_invites', 'supervisor_invites', 'admin_password_resets'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedSmallInteger('failed_attempts')->default(0);
            });
        }
    }

    public function down(): void
    {
        foreach (['admin_invites', 'supervisor_invites', 'admin_password_resets'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn('failed_attempts');
            });
        }
    }
};
