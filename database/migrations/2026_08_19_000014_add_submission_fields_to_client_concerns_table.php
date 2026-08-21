<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('client_concerns', function (Blueprint $table) {
            $table->unsignedBigInteger('related_service_id')->nullable()->after('client_id');
            $table->enum('submitted_by', ['client', 'staff'])->default('staff')->after('status');
            $table->boolean('reviewed')->default(false)->after('submitted_by');
            $table->foreign('related_service_id')->references('id')->on('tracker_services')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('client_concerns', function (Blueprint $table) {
            $table->dropForeign(['related_service_id']);
            $table->dropColumn(['related_service_id', 'submitted_by', 'reviewed']);
        });
    }
};
