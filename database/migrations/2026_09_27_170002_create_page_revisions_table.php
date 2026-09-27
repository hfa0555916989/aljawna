<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل نسخ الصفحات (docs/SPEC.md §9 page_revisions): لا يُحذف منه شيء.
     *
     * is_baseline يعلِّم "نسخة الأساس" المحمية للتصميم الأساسي (قرار المالك في
     * docs/DECISIONS.md)، ولا تكون لصفحة إلا نسخة أساس واحدة.
     */
    public function up(): void
    {
        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->restrictOnDelete();
            $table->jsonb('blocks');
            $table->foreignId('author_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_baseline')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['page_id', 'id']);
        });

        DB::statement('CREATE UNIQUE INDEX page_revisions_one_baseline_per_page ON page_revisions (page_id) WHERE is_baseline');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('page_revisions');
    }
};
