<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The ADD — Cash Received block of the Cash Daily Position worksheet records
 * money that came in on a day: a mirror of a confirmed client payment, or a
 * free-text receipt. To link a receipt to the client it belongs to, and to
 * stop the same confirmed payment being recorded twice, cash_daily_lines needs
 * two optional references.
 *
 * - client_id:              the client a client-linked receipt belongs to.
 * - source_cash_payment_id: the confirmed cash_payments row this addition was
 *                           created from. A UNIQUE index makes recording the
 *                           same confirmed payment twice impossible.
 *
 * Additive and non-destructive: two new nullable columns and one unique index.
 * No existing column, row or constraint is altered; no backfill is required.
 * The unique index ignores NULLs by definition, so free-text receipts (with no
 * source payment) are unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_daily_lines', function (Blueprint $table) {
            $table->foreignId('client_id')
                ->nullable()
                ->after('cash_daily_position_id')
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('source_cash_payment_id')
                ->nullable()
                ->after('amount')
                ->constrained('cash_payments')
                ->nullOnDelete();

            // One confirmed payment can back at most one receipt line.
            $table->unique('source_cash_payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('cash_daily_lines', function (Blueprint $table) {
            $table->dropUnique(['source_cash_payment_id']);
            $table->dropForeign(['source_cash_payment_id']);
            $table->dropColumn('source_cash_payment_id');
            $table->dropForeign(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
