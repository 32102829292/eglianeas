<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One frozen payment line of a saved Cash Daily Position Report.
 *
 * Copies each cash payment that fell in the report's period, keeping its TRUE
 * paid_on date (a weekend payment stays on its weekend date even though the
 * worksheet only shows weekday rows), its amount, method and status.
 *
 * `cash_payment_id` links back to the original cash_payments row for audit and
 * is SET NULL on delete: if a source payment is ever removed the frozen report
 * still stands on its own copy.
 *
 * Cascades only from the parent report / day; no financial source table is
 * touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_daily_report_payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('cash_daily_report_id')
                ->constrained('cash_daily_reports')
                ->cascadeOnDelete();

            $table->foreignId('cash_daily_report_day_id')
                ->nullable()
                ->constrained('cash_daily_report_days')
                ->cascadeOnDelete();

            // Audit link to the original payment. SET NULL so the frozen
            // snapshot survives removal of the source row.
            $table->foreignId('cash_payment_id')
                ->nullable()
                ->constrained('cash_payments')
                ->nullOnDelete();

            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            $table->string('method', 30)->nullable();
            $table->string('status', 20);               // partial | paid
            $table->string('reference', 120)->nullable();

            $table->timestamps();

            $table->index('cash_daily_report_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_daily_report_payments');
    }
};
