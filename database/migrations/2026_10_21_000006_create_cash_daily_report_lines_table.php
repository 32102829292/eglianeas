<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One frozen salary / expense line of a saved Cash Daily Position Report.
 *
 * These are the report's "LESS" block, copied from the live cash_daily_lines at
 * save time so the saved report keeps the exact deductions it was built with.
 *
 * `source_line_id` is a SOFT audit reference to the original cash_daily_lines
 * row. It is deliberately NOT a foreign key, so editing or deleting a live line
 * later can never cascade into — or invalidate — this frozen snapshot.
 *
 * Cascades only from the parent report / day; no financial source table is
 * touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_daily_report_lines', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_daily_report_id')
                ->constrained('cash_daily_reports')
                ->cascadeOnDelete();

            $table->foreignId('cash_daily_report_day_id')
                ->nullable()
                ->constrained('cash_daily_report_days')
                ->cascadeOnDelete();

            $table->string('type', 20);                 // salary | expense
            $table->string('category', 60)->nullable();
            $table->string('label', 150);
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();

            // Original cash_daily_lines.id — audit only, not a hard FK.
            $table->unsignedBigInteger('source_line_id')->nullable();

            $table->timestamps();

            $table->index('cash_daily_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_daily_report_lines');
    }
};
