<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a billing line item self-describing about *when* it is charged and
 * *why* it was included.
 *
 * Nothing here rewrites existing rows: `frequency` is nullable and
 * `manual_include` defaults to false, so every statement created before this
 * migration keeps its exact amounts, labels and totals. Historical integrity is
 * preserved because amounts were already snapshotted onto
 * billing_line_items.amount — this migration only adds the scheduling context
 * that the billing page needs to render an explicit include/exclude control.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_line_items', function (Blueprint $table) {
            // How often the underlying service recurs (monthly/quarterly/annual/
            // one_time/as_needed). Null on legacy rows and left null when the
            // category+form type give no meaningful frequency.
            $table->string('frequency', 20)->nullable()->after('fee_rate_id');

            // True when the admin included a service that is not due this period
            // (an annual return billed outside its usual quarter, for example).
            // Purely an audit flag for the statement; it never alters the total.
            $table->boolean('manual_include')->default(false)->after('frequency');

            // Free-text detail for ad-hoc items (custom billing rows).
            $table->text('notes')->nullable()->after('manual_include');
        });
    }

    public function down(): void
    {
        Schema::table('billing_line_items', function (Blueprint $table) {
            $table->dropColumn(['frequency', 'manual_include', 'notes']);
        });
    }
};