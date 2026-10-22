<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Cash Daily Position report is a per-day worksheet: how much money came
 * in (excluding the beginning balance), what was spent (salaries and other
 * expenses), and therefore the expected/actual cash position at the end of the
 * day. The incoming money itself is never retyped here — it is read from the
 * billings already recorded — so this table only stores the figures an
 * accountant supplies by hand.
 *
 * cash_daily_positions holds one row per day; cash_daily_lines holds the
 * salary / expense lines beneath it (the report's "LESS" block). Balances and
 * totals are computed by the service, not stored, so the report can never
 * disagree with the payments it summarises.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_daily_positions', function (Blueprint $table) {
            $table->id();
            $table->date('position_date')->unique();
            $table->decimal('beginning_balance', 12, 2)->default(0);
            $table->decimal('budget', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('position_date');
        });

        Schema::create('cash_daily_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_daily_position_id')->constrained('cash_daily_positions')->cascadeOnDelete();
            $table->string('type', 20)->default('expense');
            $table->string('category', 60)->nullable();
            $table->string('label', 150);
            $table->decimal('amount', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('cash_daily_position_id');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_daily_lines');
        Schema::dropIfExists('cash_daily_positions');
    }
};
