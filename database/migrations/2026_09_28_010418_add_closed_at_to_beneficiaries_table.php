<?php

declare(strict_types=1);

use App\Actions\Beneficiaries\ChangeBeneficiaryStatus;
use App\BeneficiaryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تاريخ إقفال المبادرة: أساس حذف صور إيصالاتها بعد مدة الاحتفاظ (T21، docs/DECISIONS.md).
     * يُضبط عند الإغلاق ويُفرَّغ عند إعادة الفتح. المغلقة حاليًا تأخذ تاريخ آخر
     * "beneficiary.closed" في سجل التدقيق، ومن لا سجل لها يبقى فارغًا فلا تُحذف إيصالاتها.
     */
    public function up(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->timestamp('closed_at')->nullable()->index();
        });

        DB::statement(
            'update beneficiaries set closed_at = (
                select max(audit_logs.created_at) from audit_logs
                where audit_logs.action = ?
                  and audit_logs.subject_type = ?
                  and audit_logs.subject_id = beneficiaries.id
            ) where status = ?',
            [ChangeBeneficiaryStatus::AUDIT_CLOSED, 'App\\Models\\Beneficiary', BeneficiaryStatus::Closed->value],
        );
    }

    public function down(): void
    {
        Schema::table('beneficiaries', function (Blueprint $table): void {
            $table->dropColumn('closed_at');
        });
    }
};
