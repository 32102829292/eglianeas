<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('weekly_bookkeeping_id')->nullable()->after('tracker_instance_id')->constrained('weekly_bookkeeping')->nullOnDelete();
            $table->index('weekly_bookkeeping_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weekly_bookkeeping_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('weekly_bookkeeping_id');
        });
    }
};