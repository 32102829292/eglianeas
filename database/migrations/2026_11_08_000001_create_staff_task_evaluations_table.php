<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_task_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('staff_id')->constrained('users')->onDelete('cascade');
            $table->string('source_type'); // kaizen_concern / admin_concern
            $table->unsignedBigInteger('source_id');
            $table->foreignId('evaluation_period_id')->nullable()->constrained('staff_evaluation_periods')->onDelete('set null');
            $table->unsignedInteger('accomplishment_quality_score')->nullable(); // 0-100
            $table->unsignedInteger('timeliness_score')->nullable(); // 0-100
            $table->unsignedInteger('immediate_responsiveness_score')->nullable(); // 0-100
            $table->unsignedInteger('client_feedback_score')->nullable(); // 0-100
            $table->unsignedInteger('overall_score')->nullable(); // 0-100
            $table->string('performance_grade')->nullable();
            $table->string('evaluation_status')->default('pending'); // pending/provisional/finalized/adjusted/reversed
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->date('deadline_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->text('adjustment_reason')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'source_type', 'source_id'], 'staff_eval_source_idx');
            $table->index(['evaluation_period_id'], 'staff_eval_period_idx');
            $table->index(['evaluation_status'], 'staff_eval_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_task_evaluations');
    }
};
