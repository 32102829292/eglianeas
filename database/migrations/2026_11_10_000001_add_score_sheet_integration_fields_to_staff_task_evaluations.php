<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('staff_task_evaluations', function (Blueprint $table) {
            // Confirms that this evaluation has actually been linked to the
            // staff member's Score Sheet for its evaluation period. This is
            // deliberately separate from "the task was completed" so a badge
            // can never claim integration before the link exists.
            $table->boolean('score_sheet_included')->default(false)->after('adjustment_reason');
            $table->timestamp('score_sheet_added_at')->nullable()->after('score_sheet_included');
            $table->decimal('score_sheet_weighted_points', 8, 2)->nullable()->after('score_sheet_added_at');
            $table->string('score_sheet_criterion')->nullable()->after('score_sheet_weighted_points');
            $table->timestamp('score_sheet_reversed_at')->nullable()->after('score_sheet_criterion');
            $table->text('score_sheet_reversal_reason')->nullable()->after('score_sheet_reversed_at');

            $table->index(['score_sheet_included'], 'staff_eval_score_sheet_included_idx');
        });

        // Existing finalized/adjusted evaluations already fed the score sheet
        // before the explicit link existed. Backfill them so the new badge and
        // source indicators do not regress for records created earlier.
        DB::table('staff_task_evaluations')
            ->whereIn('evaluation_status', ['finalized', 'adjusted'])
            ->whereNotNull('overall_score')
            ->whereNotNull('staff_id')
            ->update([
                'score_sheet_included' => true,
                'score_sheet_added_at' => DB::raw('COALESCE(finalized_at, updated_at, created_at)'),
                'score_sheet_weighted_points' => DB::raw('ROUND(overall_score * 0.50, 2)'),
                'score_sheet_criterion' => 'quality',
            ]);

        // One evaluation per source record. This is the database-level guard
        // against duplicate scoring when a task is resubmitted or updated; only
        // add it when historical data is already unique so a legacy duplicate
        // cannot block the migration.
        $hasDuplicates = DB::table('staff_task_evaluations')
            ->select('source_type', 'source_id')
            ->groupBy('source_type', 'source_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if (! $hasDuplicates) {
            Schema::table('staff_task_evaluations', function (Blueprint $table) {
                $table->unique(['source_type', 'source_id'], 'staff_eval_source_unique');
            });
        }
    }

    public function down(): void
    {
        // The unique index is skipped when historical duplicates existed, so
        // tolerate it being absent during rollback.
        try {
            Schema::table('staff_task_evaluations', function (Blueprint $table) {
                $table->dropUnique('staff_eval_source_unique');
            });
        } catch (\Throwable $e) {
            // Index was never created.
        }

        Schema::table('staff_task_evaluations', function (Blueprint $table) {
            $table->dropIndex('staff_eval_score_sheet_included_idx');
            $table->dropColumn([
                'score_sheet_included',
                'score_sheet_added_at',
                'score_sheet_weighted_points',
                'score_sheet_criterion',
                'score_sheet_reversed_at',
                'score_sheet_reversal_reason',
            ]);
        });
    }
};
