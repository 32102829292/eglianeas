<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('weekly_bookkeeping_targets', function (Blueprint $table) {
            // 1️⃣ Admin‑chosen start date (nullable, no default)
            $table->date('selected_date')->nullable()
                ->after('target_date');

            // 2️⃣ Priority level – nullable VARCHAR with a comment
            //    Application‑level validation enforces low|medium|high;
            //    a PostgreSQL CHECK could be added separately if desired.
            $table->string('priority', 19)
                ->nullable()
                ->after('selected_date')
                ->comment('low|medium|high');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_bookkeeping_targets', function (Blueprint $table) {
            $table->dropColumn('selected_date');
            $table->dropColumn('priority');
        });
    }
};