<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * الصفحات (docs/SPEC.md §9 pages، FR-50..56). blocks هي المسودة الحالية؛ النسخة
     * المنشورة هي آخر صف لها في page_revisions (المعاينة لا تنشر، والنشر يكتب نسخة).
     */
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title', 150);
            $table->string('seo_description', 300)->nullable();
            $table->jsonb('blocks')->default('[]');
            $table->string('status', 20)->default('draft');
            $table->boolean('is_system')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
