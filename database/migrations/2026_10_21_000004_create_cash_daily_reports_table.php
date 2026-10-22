<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The saved Cash Daily Position Report header.
 *
 * A "report" is a frozen snapshot of one client's daily cash position for one
 * accounting period (week / month / quarter). It stores the resolved period
 * bounds and the computed period totals AS THEY WERE at save time, so reopening
 * a saved report never recalculates historical figures from live records.
 *
 * Nothing existing is altered: the live worksheet still reads cash_daily_positions
 * / cash_daily_lines / cash_payments directly. This table is purely additive.
 *
 * Deleting a report cascades only to its own snapshot children (report days,
 * lines, payments). It never cascades to users, cash_payments, billings or any
 * bookkeeping target.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_daily_reports', function (Blueprint $table) {
            $table->id();

            // The client this report belongs to. RESTRICT: a report must never
            // be silently deleted by removing a user account.
            $table->foreignId('client_id')->constrained('users')->restrictOnDelete();

            $table->string('period_type', 10);          // week | month | quarter
            $table->smallInteger('year');
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('week')->nullable();
            $table->unsignedTinyInteger('quarter')->nullable();

            // Frozen inclusive date range, resolved at save time.
            $table->date('period_start');
            $table->date('period_end');

            // A stable, server-generated key encoding the exact period
            // ("w:2026-10-2" / "m:2026-10" / "q:2026-4"). Combined with
            // client_id it gives a database-enforced uniqueness guarantee that
            // works across PostgreSQL and the SQLite test database, unlike a
            // composite unique over nullable period columns.
            $table->string('period_key', 30);

            // Period-level opening figures and frozen computed totals.
            $table->decimal('beginning_balance', 12, 2)->default(0);
            $table->decimal('budget', 12, 2)->default(0);
            $table->decimal('total_collected', 12, 2)->default(0);
            $table->decimal('total_salaries', 12, 2)->default(0);
            $table->decimal('total_expenses', 12, 2)->default(0);
            $table->decimal('total_less', 12, 2)->default(0);
            $table->decimal('total_budget', 12, 2)->default(0);
            $table->decimal('total_over_short', 12, 2)->default(0);

            $table->string('label', 150)->nullable();
            $table->text('notes')->nullable();

            // Audit: who saved the report. SET NULL so the record survives a
            // user removal without taking financial history with it.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['client_id', 'period_type', 'year']);
            $table->index(['client_id', 'period_start']);

            // Duplicate-report prevention, enforced by the database.
            $table->unique(['client_id', 'period_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_daily_reports');
    }
};
