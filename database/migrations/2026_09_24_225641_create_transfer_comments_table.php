<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * تعليقات المشرف المسند إليه على الحوالات المتكررة (docs/SPEC.md §9 transfer_comments, FR-45).
     * تُحفظ ولا تُعدَّل.
     */
    public function up(): void
    {
        Schema::create('transfer_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('transfer_id')->constrained('transfers')->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->text('body');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_comments');
    }
};
