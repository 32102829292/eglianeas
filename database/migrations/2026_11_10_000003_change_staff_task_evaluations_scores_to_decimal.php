<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The scoring service computes decimal overall scores (e.g. 45.6) via
     * combineScores(), but the score columns were defined as integers.
     * PostgreSQL rejects decimal values for integer columns, causing
     * SQLSTATE[22P02] errors when finalising Admin Concern evaluations.
     *
     * Change all score columns to decimal(5,2) to preserve decimal precision.
     */
    public function up(): void
    {
        Schema::table('staff_task_evaluations', function (Blueprint $table) {
            $table->decimal('accomplishment_quality_score', 5, 2)->nullable()->change();
            $table->decimal('timeliness_score', 5, 2)->nullable()->change();
            $table->decimal('immediate_responsiveness_score', 5, 2)->nullable()->change();
            $table->decimal('client_feedback_score', 5, 2)->nullable()->change();
            $table->decimal('overall_score', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Changing decimal back to integer may truncate fractional values. This
     * is only safe if all stored values happen to be whole numbers.
     */
    public function down(): void
    {
        Schema::table('staff_task_evaluations', function (Blueprint $table) {
            $table->unsignedInteger('accomplishment_quality_score')->nullable()->change();
            $table->unsignedInteger('timeliness_score')->nullable()->change();
            $table->unsignedInteger('immediate_responsiveness_score')->nullable()->change();
            $table->unsignedInteger('client_feedback_score')->nullable()->change();
            $table->unsignedInteger('overall_score')->nullable()->change();
        });
    }
};
