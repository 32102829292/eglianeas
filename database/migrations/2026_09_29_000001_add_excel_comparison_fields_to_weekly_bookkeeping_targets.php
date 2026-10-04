<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the weekly bookkeeping tables with the comparison workbook
 * ("Comparison of weekly bookkeeping status"): per-task staff assignment,
 * target-vs-actual timing and payment detail for Billing / Payment targets.
 *
 * The workbook assigns staff per task, not per client, so the assignee moves
 * from the weekly plan down onto each individual task row.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_bookkeeping_targets', function (Blueprint $table) {
            // Each task carries its own assignee. A client may have different
            // staff for Pick-Up, Record, Return / Collect and Billing / Payment.
            $table->foreignId('assigned_staff_id')->nullable()->after('task_type')->constrained('users')->nullOnDelete();
            $table->string('assigned_staff_name', 120)->nullable()->after('assigned_staff_id');

            // Payment detail for Billing / Payment targets, mirroring the
            // workbook's "paid cash <date>" / "paid gcash <date>" entries.
            $table->string('payment_method', 30)->nullable()->after('ended_at');
            $table->timestamp('payment_at')->nullable()->after('payment_method');

            // Denormalised target-vs-actual outcome, so the weekly grid and the
            // printed report can be read without recomputing every row.
            $table->string('timing', 20)->nullable()->after('actual_status');
        });

        Schema::table('weekly_bookkeeping_targets', function (Blueprint $table) {
            $table->index('assigned_staff_id');
            $table->index('timing');
            $table->index(['actual_status', 'timing']);
        });

        // The comparison workbook keeps one sheet per week, so a week is the
        // planning unit: everyone who staffs that week contributes tasks to
        // the same plan instead of creating a competing one.
        Schema::table('weekly_bookkeeping', function (Blueprint $table) {
            $table->unique('week_start', 'weekly_bookkeeping_week_start_unique');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_bookkeeping', function (Blueprint $table) {
            $table->dropUnique('weekly_bookkeeping_week_start_unique');
        });

        Schema::table('weekly_bookkeeping_targets', function (Blueprint $table) {
            $table->dropIndex(['assigned_staff_id']);
            $table->dropIndex(['timing']);
            $table->dropIndex(['actual_status', 'timing']);
            $table->dropColumn([
                'assigned_staff_id',
                'assigned_staff_name',
                'payment_method',
                'payment_at',
                'timing',
            ]);
        });
    }
};
