<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_contribution_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->string('activity_type'); // kaizen_submitted / kaizen_implemented / admin_concern_completed
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->foreignId('evaluation_period_id')->nullable()->constrained('staff_evaluation_periods')->onDelete('set null');
            $table->unsignedInteger('points'); // contribution points
            $table->string('status')->default('awarded'); // awarded/reversed
            $table->timestamp('awarded_at');
            $table->timestamps();

            $table->index(['staff_id', 'activity_type'], 'staff_contrib_activity_idx');
            $table->index(['evaluation_period_id'], 'staff_contrib_period_idx');
            $table->index(['status'], 'staff_contrib_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_contribution_records');
    }
};
