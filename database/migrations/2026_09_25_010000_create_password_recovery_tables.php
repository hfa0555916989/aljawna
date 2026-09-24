<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * يستبدل جدول Laravel الافتراضي password_reset_tokens (email/token)
 * بجدول المواصفة §9، ويضيف طلبات الاستعادة وسجلها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('password_reset_tokens');

        Schema::create('password_reset_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('status');
            $table->foreignId('claimed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('claimed_until')->nullable();
            $table->string('requested_ip', 45);
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('password_reset_requests')->restrictOnDelete();
            $table->foreignId('issued_by')->constrained('users')->restrictOnDelete();
            $table->string('token_hash')->unique();
            $table->string('sent_to_phone');
            $table->boolean('is_other_number');
            $table->string('reason')->nullable();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('recovery_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('request_id')->constrained('password_reset_requests')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('performed_by')->constrained('users')->restrictOnDelete();
            $table->string('action');
            $table->string('old_phone')->nullable();
            $table->string('new_phone')->nullable();
            $table->string('sent_to_phone')->nullable();
            $table->string('reason')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_logs');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('password_reset_requests');

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
