<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The handwritten Cash Daily Position worksheet reconciles the EXPECTED ending
 * balance against the ACTUAL cash the accountant physically counted, and shows
 * the overage / (shortage) between them. The existing tables track beginning
 * balance, budget, salaries and expenses, but have no place to record that
 * counted cash.
 *
 * This migration adds a single nullable `actual_cash` column to the live day
 * table and to the frozen snapshot day table. Null means "not yet counted" and
 * is deliberately distinct from a counted zero.
 *
 * Additive and non-destructive: two new nullable columns. No existing column,
 * row or constraint is altered, and no backfill is required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_daily_positions', function (Blueprint $table) {
            $table->decimal('actual_cash', 12, 2)->nullable()->after('budget');
        });

        Schema::table('cash_daily_report_days', function (Blueprint $table) {
            $table->decimal('actual_cash', 12, 2)->nullable()->after('ending_balance');
        });
    }

    public function down(): void
    {
        Schema::table('cash_daily_positions', function (Blueprint $table) {
            $table->dropColumn('actual_cash');
        });

        Schema::table('cash_daily_report_days', function (Blueprint $table) {
            $table->dropColumn('actual_cash');
        });
    }
};
