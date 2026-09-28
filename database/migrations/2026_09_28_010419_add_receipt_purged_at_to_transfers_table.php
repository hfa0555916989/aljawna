<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * حذف صورة الإيصال بعد مدة الاحتفاظ (T21): يُفرَّغ receipt_path ويُسجَّل وقت الحذف،
     * وتبقى بيانات الحوالة كاملة (المبلغ والتاريخ والمبادر وبصمة الإيصال لكشف التكرار).
     * القيد يمنع حوالة بلا إيصال ما لم تُحذف صورته بسياسة الاحتفاظ.
     */
    public function up(): void
    {
        Schema::table('transfers', function (Blueprint $table): void {
            $table->string('receipt_path')->nullable()->change();
            $table->timestamp('receipt_purged_at')->nullable();
        });

        DB::statement('alter table transfers add constraint transfers_receipt_present_check check (receipt_path is not null or receipt_purged_at is not null)');
    }

    public function down(): void
    {
        DB::statement('alter table transfers drop constraint transfers_receipt_present_check');

        Schema::table('transfers', function (Blueprint $table): void {
            $table->dropColumn('receipt_purged_at');
            $table->string('receipt_path')->nullable(false)->change();
        });
    }
};
