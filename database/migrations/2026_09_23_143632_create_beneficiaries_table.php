<?php

declare(strict_types=1);

use App\BeneficiaryStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * المستفيدون ومبادراتهم وحساباتهم البنكية (docs/SPEC.md §9 beneficiaries).
     */
    public function up(): void
    {
        Schema::create('beneficiaries', function (Blueprint $table) {
            $table->id();
            $table->string('display_name');
            $table->string('account_holder');
            $table->string('bank_name');
            $table->string('account_number', 30);
            $table->string('iban', 24)->unique();
            $table->decimal('target_amount', 12, 2);
            $table->date('target_deadline');
            $table->date('recommended_deadline');
            $table->date('wedding_date');
            $table->enum('status', array_column(BeneficiaryStatus::cases(), 'value'))
                ->default(BeneficiaryStatus::Active->value)
                ->index();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable()->index();
            $table->timestamp('approval_revoked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('beneficiaries');
    }
};
