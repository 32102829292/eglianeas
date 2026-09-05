<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('tracker_instance_id')->nullable()->after('user_id')->constrained('tracker_instances')->nullOnDelete();
            $table->index('tracker_instance_id');
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tracker_instance_id');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropColumn('tracker_instance_id');
        });
    }
};