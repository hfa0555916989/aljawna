<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جيل جلسات المستخدم دائم في قاعدة البيانات، فلا يُفقد إنهاء الجلسات القسري بتفريغ التخزين المؤقت (docs/SPEC.md §4.2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('session_epoch')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('session_epoch');
        });
    }
};
