<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * عناصر قائمتي الرأس والتذييل (docs/SPEC.md §9 menu_items). كل عنصر يشير إلى
     * صفحة أو إلى رابط، لا إلى الاثنين ولا إلى لا شيء.
     */
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('location', 20);
            $table->string('label', 40);
            $table->foreignId('page_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('url', 500)->nullable();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['location', 'position']);
        });

        DB::statement('ALTER TABLE menu_items ADD CONSTRAINT menu_items_page_or_url CHECK ((page_id IS NULL) <> (url IS NULL))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
