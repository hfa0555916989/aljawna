<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * رسائل "اتصل بنا" (docs/SPEC.md §9 contact_messages، FR-55): تُقرأ بصلاحية
     * messages.view فقط. read_at يُسجَّل عند وسمها "مقروءة" من اللوحة.
     */
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('phone', 20);
            $table->string('body', 1000);
            $table->string('ip', 45);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('read_at')->nullable();

            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
