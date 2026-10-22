<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One frozen weekday row of a saved Cash Daily Position Report.
 *
 * Each row is a snapshot of a single weekday's figures exactly as the live
 * worksheet produced them at save time: the carried-in beginning balance, what
 * was collected (including any weekend activity folded in), the budget, the
 * salaries / expenses, the resulting ending cash position and the
 * overage/(shortage). The report is reopened from these stored numbers, never
 * recalculated from live data.
 *
 * Cascades only from the parent report; no financial source table is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_daily_report_days', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_daily_report_id')
                ->constrained('cash_daily_reports')
                ->cascadeOnDelete();

            $table->date('report_date');

            $table->decimal('beginning_balance', 12, 2)->default(0);
            $table->decimal('collected', 12, 2)->default(0);
            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('salaries', 12, 2)->default(0);
            $table->decimal('expenses', 12, 2)->default(0);
            $table->decimal('less', 12, 2)->default(0);
            $table->decimal('ending_balance', 12, 2)->default(0);
            $table->decimal('over_short', 12, 2)->default(0);

            // Whether the beginning balance was carried forward from a prior
            // day, and whether weekend activity was folded into this row.
            $table->boolean('auto_beginning')->default(false);
            $table->boolean('has_weekend_fold')->default(false);

            $table->timestamps();

            $table->index(['cash_daily_report_id', 'report_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_daily_report_days');
    }
};
