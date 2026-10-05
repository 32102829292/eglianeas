<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets the accountability timeline point at a monthly or quarterly plan the
 * same way it already points at a weekly one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('monthly_bookkeeping_id')->nullable()->after('weekly_bookkeeping_id')->constrained('monthly_bookkeeping')->nullOnDelete();
            $table->index('monthly_bookkeeping_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('quarterly_bookkeeping_id')->nullable()->after('monthly_bookkeeping_id')->constrained('quarterly_bookkeeping')->nullOnDelete();
            $table->index('quarterly_bookkeeping_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quarterly_bookkeeping_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('monthly_bookkeeping_id');
        });
    }
};
