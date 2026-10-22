<?php

namespace App\Support\Cash;

use App\Models\Billing;
use App\Models\CashPayment;
use App\Models\User;
use App\Support\Bookkeeping\Concerns\HasBookkeepingTargetWorkflow;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Confirms a Billing / Payment for one bookkeeping target and records the
 * money actually received.
 *
 * The important word is ONE. A payment is always tied to a specific target
 * (billable), so confirming it flips that single target's payment columns and
 * leaves every sibling in the same client/period untouched. This is the exact
 * behaviour the old client+period "paid billing" heuristic could not provide.
 *
 * Amount due is read from the client's active Billing — never invented here —
 * so a payment can be recognised as partial or full without guessing.
 */
class PaymentSynchronizer
{
    /**
     * Confirm the payment for a single target.
     *
     * @param  Model  $target  a Weekly/Monthly/QuarterlyBookkeepingTarget
     * @param  float  $amount  money received in this confirmation
     * @param  string|null  $method  cash / gcash / other
     * @param  \Carbon\CarbonInterface|string|null  $paidOn  the actual payment date
     * @param  User|null  $actor
     * @param  string|null  $idempotencyKey  provided by callers; auto-generated when absent
     */
    public function confirm(
        Model $target,
        float $amount,
        ?string $method = null,
        $paidOn = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
        ?string $notes = null,
    ): CashPayment {
        $this->assertSyncable($target);

        if ($amount <= 0) {
            throw new InvalidArgumentException('A payment amount must be greater than zero.');
        }

        $paidOn = $paidOn === null ? now() : \Illuminate\Support\Carbon::parse($paidOn);
        $idempotencyKey = $idempotencyKey ?: $this->defaultKey($target);

        return DB::transaction(function () use ($target, $amount, $method, $paidOn, $actor, $idempotencyKey, $notes) {
            $existing = CashPayment::where('idempotency_key', $idempotencyKey)->first();

            if ($existing) {
                // Re-confirming the same payment is a no-op: never double-book.
                $this->applyTargetPaymentState($target, $method, $paidOn, $existing->status, $existing->amount);

                return $existing;
            }

            $billing = $this->resolveBilling($target);
            $confirmedTotal = $this->confirmedTotal($target) + $amount;
            $due = $this->amountDue($billing);
            $status = ($due !== null && $confirmedTotal + 0.00001 < $due)
                ? CashPayment::STATUS_PARTIAL
                : CashPayment::STATUS_PAID;

            $payment = CashPayment::create([
                'billable_type' => $target->getMorphClass(),
                'billable_id' => $target->getKey(),
                'client_id' => $target->client_id,
                'billing_id' => $billing?->id,
                'amount' => $amount,
                'paid_on' => $paidOn,
                'method' => $method,
                'status' => $status,
                'idempotency_key' => $idempotencyKey,
                'notes' => $notes,
                'recorded_by' => $actor?->id,
            ]);

            $this->applyTargetPaymentState($target, $method, $paidOn, $status, (float) $confirmedTotal);

            return $payment;
        });
    }

    /**
     * The target monthly/weekly/quarterly Billing / Payment row this payment
     * belongs to must be a payment task. Anything else is a caller bug, not a
     * user error, so it throws.
     */
    private function assertSyncable(Model $target): void
    {
        $isTarget = in_array(HasBookkeepingTargetWorkflow::class, class_uses_recursive($target), true)
            || method_exists($target, 'paidBilling');

        if (! $isTarget) {
            throw new InvalidArgumentException('Payment synchronisation requires a bookkeeping target.');
        }

        if (($target->task_type ?? null) !== 'payment') {
            throw new InvalidArgumentException('Only a Billing / Payment target can be marked paid.');
        }
    }

    private function resolveBilling(Model $target): ?Billing
    {
        return Billing::query()
            ->where('client_id', $target->client_id)
            ->activeOnly()
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->first();
    }

    private function amountDue(?Billing $billing): ?float
    {
        if ($billing === null) {
            return null;
        }

        return (float) $billing->total;
    }

    private function confirmedTotal(Model $target): float
    {
        return (float) CashPayment::query()
            ->where('billable_type', $target->getMorphClass())
            ->where('billable_id', $target->getKey())
            ->sum('amount');
    }

    /**
     * Write the payment outcome onto the ONE target. `payment_status` and the
     * existing `payment_method` / `payment_at` columns are set together so the
     * target's own record matches the ledger. A partial payment leaves a
     * balance; a full one clears it.
     */
    private function applyTargetPaymentState(
        Model $target,
        ?string $method,
        \Carbon\CarbonInterface $paidOn,
        string $status,
        float $confirmedTotal,
    ): void {
        $billing = $this->resolveBilling($target);
        $due = $this->amountDue($billing);
        $balance = $due === null ? null : max(0, round($due - $confirmedTotal, 2));

        $target->payment_method = $method ?? $target->payment_method;

        if ($status === CashPayment::STATUS_PAID) {
            $target->payment_status = $target::PAYMENT_STATUS_PAID;
            $target->payment_at = $paidOn;
            $target->balance_amount = $balance;
            $target->actual_status = $target::ACTUAL_STATUS_PAID;
        } else {
            $target->payment_status = $target::PAYMENT_STATUS_WITH_BALANCE;
            $target->payment_at = $paidOn;
            $target->balance_amount = $balance;
        }

        $target->save();
    }

    private function defaultKey(Model $target): string
    {
        return 'target:'.$target->getKey().':'.Str::uuid()->toString();
    }
}
