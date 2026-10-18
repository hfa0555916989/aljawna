<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ارتفاع الشعار في رأس الموقع بالبكسل، يضبطه المدير من صفحة الهوية.
     */
    public function up(): void
    {
        Schema::table('site_branding', function (Blueprint $table) {
            $table->unsignedSmallInteger('logo_height')->default(32)->after('icon_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('site_branding', function (Blueprint $table) {
            $table->dropColumn('logo_height');
        });
    }
};
