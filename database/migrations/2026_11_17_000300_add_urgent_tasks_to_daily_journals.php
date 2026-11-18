<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddUrgentTasksToDailyJournals extends Migration
{
    public function up(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            $table->json('urgent_tasks')->nullable()->after('achievement_clients');
        });
    }

    public function down(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            $table->dropColumn('urgent_tasks');
        });
    }
}