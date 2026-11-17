<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            // Clients chosen per activity code, e.g. {"F1":[12,15],"Q":[3],"R":[3,9]}.
            $table->json('achievement_clients')->nullable()->after('achievement_codes');
        });
    }

    public function down(): void
    {
        Schema::table('daily_journals', function (Blueprint $table) {
            $table->dropColumn('achievement_clients');
        });
    }
};