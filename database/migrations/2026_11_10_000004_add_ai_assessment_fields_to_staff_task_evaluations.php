<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add AI assessment tracking fields to staff_task_evaluations so Admin
     * Concerns can reuse the existing Daily Accomplishment assessment engine.
     *
     * These fields distinguish AI-generated preliminary assessments from
     * reviewer-verified final scores, preserve the original AI score for
     * score-sheet display, and store the detailed AI explanation.
     */
    public function up(): void
    {
        Schema::table('staff_task_evaluations', function (Blueprint $table) {
            $table->string('assessment_source')->nullable()
                ->after('adjustment_reason')
                ->comment('ai, rule_based, or reviewer');
            $table->decimal('original_ai_score', 5, 2)->nullable()
                ->after('assessment_source')
                ->comment('Original AI/rule-based score before any admin adjustment');
            $table->text('assessment_explanation')->nullable()
                ->after('original_ai_score')
                ->comment('Detailed AI or rule-based explanation for each score');
            $table->timestamp('assessed_at')->nullable()
                ->after('assessment_explanation')
                ->comment('When the AI/rule-based assessment was generated');
        });
    }

    public function down(): void
    {
        Schema::table('staff_task_evaluations', function (Blueprint $table) {
            $table->dropColumn([
                'assessment_source',
                'original_ai_score',
                'assessment_explanation',
                'assessed_at',
            ]);
        });
    }
};
