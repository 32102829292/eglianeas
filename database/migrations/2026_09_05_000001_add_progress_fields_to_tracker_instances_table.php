<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tracker_instances', function (Blueprint $table) {
            $table->foreignId('other_service_id')->nullable()->after('client_id')->constrained('other_services')->nullOnDelete();
            $table->string('on_hold_reason', 255)->nullable()->after('status');
            $table->date('date_completed')->nullable()->after('date_started');

            $table->index('other_service_id');
        });
    }

    public function down(): void
    {
        Schema::table('tracker_instances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('other_service_id');
        });

        Schema::table('tracker_instances', function (Blueprint $table) {
            $table->dropColumn(['other_service_id', 'on_hold_reason', 'date_completed']);
        });
    }
};