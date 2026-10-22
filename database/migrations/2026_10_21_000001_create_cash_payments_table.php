<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Cash Daily Position report needs to record that a specific bookkeeping
 * Billing / Payment row was actually settled, and on what date. Until now the
 * only link between bookkeeping and money received was a heuristic: a target
 * was considered "paid" when the client had ANY paid billing whose
 * (year * 4 + quarter) covered the target's period. That match is
 * client + period based, so it cannot distinguish two targets in the same
 * period and cannot mark one without touching its siblings.
 *
 * cash_payments is that missing, exact link. Each row points at ONE billable
 * target (Weekly / Monthly / Quarterly bookkeeping target) through a
 * polymorphic relation, so confirming a payment marks exactly that target and
 * nothing else. The optional billing_id records the quarterly Billing the
 * payment settles, and a unique idempotency key makes re-confirming the same
 * payment a no-op instead of a double entry.
 *
 * No existing table, column or record is altered by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_payments', function (Blueprint $table) {
            $table->id();

            // The target whose Billing / Payment row this payment settles.
            // Polymorphic so one ledger serves all three bookkeeping cadences.
            $table->string('billable_type', 150);
            $table->unsignedBigInteger('billable_id');

            // Denormalised for reporting without an extra join, matching how
            // the bookkeeping targets already store client_id.
            $table->foreignId('client_id')->constrained('users')->cascadeOnDelete();

            // The quarterly Billing this payment settles, when the client has
            // one. Nullable because a weekly/monthly target may be paid before
            // (or without) a quarterly billing record.
            $table->foreignId('billing_id')->nullable()->constrained('billings')->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('paid_on');
            $table->string('method', 30)->nullable();
            $table->string('status', 20)->default('paid');

            // Guards against double-confirming the same target payment.
            // Confirmed by the service layer; never derived from client name.
            $table->string('idempotency_key', 120)->unique();

            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['billable_type', 'billable_id']);
            $table->index('client_id');
            $table->index('paid_on');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_payments');
    }
};
