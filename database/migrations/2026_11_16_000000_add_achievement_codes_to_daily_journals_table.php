<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            // Selected Activity Legend codes, e.g. ["A","B","F"].
            $table->json('achievement_codes')->nullable()->after('achievements');
        });
    }

    public function down(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            $table->dropColumn('achievement_codes');
        });
    }
};