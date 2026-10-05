<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two pieces of bookkeeping target information the schema genuinely
 * lacked. Everything here is nullable, so existing targets keep behaving exactly
 * as they did before this ran.
 *
 *  - target_date_auto marks a target date that the workflow suggested rather than
 *    one a person typed. It is the marker that lets a later Pick-Up change refresh
 *    only the dates nobody has edited by hand. Rows that predate this column stay
 *    NULL and are therefore treated as manual, which is the safe default.
 *
 *  - payment_status / balance_amount / balance_note record a client that has
 *    settled part of a bookkeeping fee. These sit alongside the existing
 *    payment_method / payment_at / actual_status columns instead of replacing
 *    them, so Billing remains the authoritative record of money received and the
 *    existing Paid / Unpaid roll-ups are untouched.
 *
 * Remarks deliberately reuse the existing `notes` column, which was already
 * validated by the target edit endpoints but had no user interface, rather than
 * adding a second overlapping text field.
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'weekly_bookkeeping_targets',
        'monthly_bookkeeping_targets',
        'quarterly_bookkeeping_targets',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->boolean('target_date_auto')->nullable()->after('target_date');
                $blueprint->string('payment_status', 20)->nullable()->after('payment_at');
                $blueprint->decimal('balance_amount', 15, 2)->nullable()->after('payment_status');
                $blueprint->text('balance_note')->nullable()->after('balance_amount');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn([
                    'target_date_auto',
                    'payment_status',
                    'balance_amount',
                    'balance_note',
                ]);
            });
        }
    }
};
