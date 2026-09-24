<?php

declare(strict_types=1);

use App\TransferReviewState;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الحوالات وإيصالاتها (docs/SPEC.md §9 transfers). تُحتسب فور إنشائها، والمراجعة اختيارية.
     */
    public function up(): void
    {
        Schema::create('transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('beneficiary_id')->constrained('beneficiaries')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('transferred_on');
            $table->string('receipt_path');
            $table->string('receipt_hash', 64)->index();
            $table->string('bank_reference', 64)->nullable()->index();
            $table->boolean('is_repeated')->default(false)->index();
            $table->enum('review_state', array_column(TransferReviewState::cases(), 'value'))
                ->default(TransferReviewState::NotReviewed->value)
                ->index();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('final_reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('final_reviewed_at')->nullable();
            $table->text('final_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'beneficiary_id', 'transferred_on']);
            $table->index(['user_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfers');
    }
};
