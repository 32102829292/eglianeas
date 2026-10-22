<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Cash Daily Position report is client-scoped: the worksheet for one client
 * must be independent of every other client's worksheet, even on the same day.
 *
 * The original table was created firm-wide, with a globally unique
 * position_date, so it could only ever hold one worksheet per day for the whole
 * company. This migration adds the missing client_id and moves the uniqueness
 * to (client_id, position_date), which is the granularity the report actually
 * needs.
 *
 * Additive and non-destructive: only new column/index/constraint work on this
 * one table. No existing table, column or row is altered. The table is empty,
 * so backfill is not required.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_daily_positions', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('id')
                ->constrained('users')
                ->cascadeOnDelete();
        });

        Schema::table('cash_daily_positions', function (Blueprint $table) {
            // Replace the firm-wide uniqueness with per-client uniqueness.
            $table->dropUnique(['position_date']);
            $table->unique(['client_id', 'position_date'], 'cash_daily_positions_client_date_unique');
            $table->index(['client_id', 'position_date'], 'cash_daily_positions_client_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('cash_daily_positions', function (Blueprint $table) {
            $table->dropUnique('cash_daily_positions_client_date_unique');
            $table->dropIndex('cash_daily_positions_client_date_index');
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });

        Schema::table('cash_daily_positions', function (Blueprint $table) {
            $table->unique('position_date');
        });
    }
};
