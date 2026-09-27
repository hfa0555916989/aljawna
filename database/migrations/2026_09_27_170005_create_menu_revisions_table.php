<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * سجل نسخ القائمتين معًا (الرأس والتذييل)، ليمكن استرجاع أي نسخة سابقة والتراجع
     * عن "استعادة التصميم الأساسي" (قرار المالك في docs/DECISIONS.md). لا يُحذف منه شيء.
     */
    public function up(): void
    {
        Schema::create('menu_revisions', function (Blueprint $table) {
            $table->id();
            $table->jsonb('items');
            $table->foreignId('author_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->boolean('is_baseline')->default(false);
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('CREATE UNIQUE INDEX menu_revisions_one_baseline ON menu_revisions (is_baseline) WHERE is_baseline');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_revisions');
    }
};
