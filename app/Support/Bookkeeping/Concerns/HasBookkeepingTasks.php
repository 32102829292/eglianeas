<?php

namespace App\Support\Bookkeeping\Concerns;

use App\Models\Billing;
use App\Models\User;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Behaviour shared by the Monthly and Quarterly bookkeeping targets.
 *
 * A copy of the Weekly Bookkeeping target rules rather than an extraction out
 * of `WeeklyBookkeepingTarget`, so the weekly model is left untouched. The two
 * new modules agree with each other through this trait.
 *
 * @property int $client_id
 * @property int|null $assigned_staff_id
 * @property int|null $performed_by_id
 * @property string $task_type
 * @property string $actual_status
 * @property \Illuminate\Support\Carbon|null $target_date
 */
trait HasBookkeepingTasks
{
    public function displayClientName(): string
    {
        return $this->client?->business_name ?: $this->client?->name ?: 'Client #'.$this->client_id;
    }

    public function taskLabel(): string
    {
        return static::TASK_TYPES[$this->task_type] ?? ucfirst((string) $this->task_type);
    }

    public function attachmentHint(): string
    {
        return static::ATTACHMENT_HINTS[$this->task_type] ?? 'Attachment';
    }

    /**
     * The outcome this task is expected to reach, based on the comparison
     * workbook: Pick-Up resolves to On Time, Record to Completed, Return to
     * Returned and Billing to Paid.
     */
    public function expectedStatus(): string
    {
        return match ($this->task_type) {
            'pickup' => static::ACTUAL_STATUS_ON_TIME,
            'record' => static::ACTUAL_STATUS_COMPLETED,
            'return' => static::ACTUAL_STATUS_RETURNED,
            'payment' => static::ACTUAL_STATUS_PAID,
            default => static::ACTUAL_STATUS_COMPLETED,
        };
    }

    public function satisfiedStatuses(): array
    {
        return static::SATISFIED_STATUSES[$this->task_type] ?? [static::ACTUAL_STATUS_COMPLETED];
    }

    /**
     * True once the task reached any of its accepted outcomes. This stays the
     * single "the work is done" predicate so status roll-ups keep working
     * regardless of which task-specific wording was recorded.
     */
    public function isCompleted(): bool
    {
        return in_array($this->actual_status, $this->satisfiedStatuses(), true);
    }

    public function isSatisfied(): bool
    {
        return $this->isCompleted();
    }

    public function isInProgress(): bool
    {
        return $this->actual_status === static::ACTUAL_STATUS_IN_PROGRESS;
    }

    public function isPending(): bool
    {
        return $this->actual_status === static::ACTUAL_STATUS_PENDING;
    }

    /**
     * Work the workbook files under "Uncollected / Unfinished": anything that
     * has not reached its expected outcome, whether still pending or explicitly
     * failed.
     */
    public function isUnfinished(): bool
    {
        return ! $this->isCompleted() && ! $this->isInProgress();
    }

    public function isUnpaid(): bool
    {
        if ($this->task_type !== 'payment' || $this->isCompleted()) {
            return false;
        }

        // Billing is the authoritative record of money received, so a client
        // already marked paid there must not reappear in the Unpaid column.
        return $this->paidBilling() === null;
    }

    /**
     * Memoized result of paidBilling() for this instance. The grid calls
     * isUnpaid()/isPaidViaBilling() several times per target, and without this
     * each call re-ran the same Billing query.
     */
    private ?Billing $paidBillingMemo = null;

    private bool $paidBillingMemoResolved = false;

    /**
     * The client's paid billing covering this target, when one exists.
     * Bookkeeping never invents payment status; it only reads what Billing
     * already recorded.
     */
    public function paidBilling(): ?Billing
    {
        if ($this->paidBillingMemoResolved) {
            return $this->paidBillingMemo;
        }

        $this->paidBillingMemoResolved = true;

        return $this->paidBillingMemo = $this->queryPaidBilling();
    }

    /**
     * Resolve the client, assignedStaff and performedBy relations for many
     * targets with a single query.
     *
     * All three relations point at the same `users` table, so eager loading them
     * independently issued up to three separate `where id in (...)` queries for
     * largely overlapping id sets.
     *
     * @param  iterable<int, self>  $targets
     */
    public static function primeUserRelations(iterable $targets): void
    {
        $ids = [];

        foreach ($targets as $target) {
            foreach (['client_id', 'assigned_staff_id', 'performed_by_id'] as $column) {
                if ($target->{$column} !== null) {
                    $ids[$target->{$column}] = true;
                }
            }
        }

        if ($ids === []) {
            return;
        }

        $users = User::query()
            ->whereIn('id', array_keys($ids))
            ->get()
            ->keyBy('id');

        $map = [
            'client' => 'client_id',
            'assignedStaff' => 'assigned_staff_id',
            'performedBy' => 'performed_by_id',
        ];

        foreach ($targets as $target) {
            foreach ($map as $relation => $column) {
                $id = $target->{$column};

                /* A belongsTo with a null FK must resolve to null, and a missing
                   or soft-deleted user must stay null rather than lazy loading. */
                $target->setRelation($relation, $id === null ? null : ($users->get($id) ?? null));
            }
        }
    }

    /**
     * Warm the paid-billing lookup for many targets using a single query.
     *
     * The list/report pages call isUnpaid() and isPaidViaBilling() for every
     * payment target, which would otherwise issue one Billing query per target.
     *
     * @param  iterable<int, self>  $targets
     */
    public static function primePaidBillings(iterable $targets): void
    {
        $byClient = [];

        foreach ($targets as $target) {
            if ($target->task_type !== 'payment' || ! $target->client_id || ! $target->target_date) {
                continue;
            }

            $byClient[$target->client_id][] = $target;
        }

        if ($byClient === []) {
            return;
        }

        $billings = Billing::query()
            ->whereIn('client_id', array_keys($byClient))
            ->where('status', Billing::STATUS_PAID)
            ->activeOnly()
            ->get()
            /* Newest paid_at first, matching the per-target latest('paid_at'). */
            ->sortByDesc(fn (Billing $b) => $b->paid_at?->getTimestamp() ?? 0)
            ->values();

        $grouped = $billings->groupBy('client_id');

        foreach ($byClient as $clientId => $clientTargets) {
            $candidates = $grouped->get($clientId, collect());

            foreach ($clientTargets as $target) {
                $period = ($target->target_date->year * 4) + (int) ceil($target->target_date->month / 3);

                $match = $candidates->first(function (Billing $billing) use ($period) {
                    if ($billing->year === null || $billing->quarter === null) {
                        return true;
                    }

                    return (($billing->year * 4) + $billing->quarter) <= $period;
                });

                $target->paidBillingMemoResolved = true;
                $target->paidBillingMemo = $match;
            }
        }
    }

    private function queryPaidBilling(): ?Billing
    {
        if (! $this->client_id || ! $this->target_date) {
            return null;
        }

        $period = ($this->target_date->year * 4) + (int) ceil($this->target_date->month / 3);

        return Billing::query()
            ->where('client_id', $this->client_id)
            ->where('status', Billing::STATUS_PAID)
            ->activeOnly()
            ->where(function ($query) use ($period) {
                $query->whereNull('year')
                    ->orWhereNull('quarter')
                    ->orWhereRaw('(year * 4 + quarter) <= ?', [$period]);
            })
            ->latest('paid_at')
            ->first();
    }

    /**
     * Billing is authoritative, so a client paid there shows as paid even when
     * the bookkeeping payment task has not been closed out yet.
     */
    public function isPaidViaBilling(): bool
    {
        return $this->task_type === 'payment' && ! $this->isCompleted() && $this->paidBilling() !== null;
    }

    /**
     * Whether the period this task was planned in has closed while the work is
     * still outstanding.
     */
    public function isPastDue(): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $end = $this->bookkeeping?->period()?->end;

        return $end !== null && now()->gt($end);
    }

    /**
     * The moment the task was actually finished, which differs per task:
     * payment records when the money was received, everything else when the
     * work was closed out.
     */
    public function actualFinishedAt(): ?Carbon
    {
        return $this->task_type === 'payment'
            ? ($this->payment_at ?? $this->ended_at)
            : $this->ended_at;
    }

    /**
     * Target vs actual. Compares the actual finish against the target date so
     * the tracker can distinguish "On Time" from "Completed Late".
     */
    public function resolveTiming(): ?string
    {
        $finishedAt = $this->actualFinishedAt();

        if (! $this->isCompleted() || ! $finishedAt || ! $this->target_date) {
            return null;
        }

        return $finishedAt->startOfDay()->lte($this->target_date->copy()->startOfDay())
            ? static::TIMING_ON_TIME
            : static::TIMING_LATE;
    }

    public function timingLabel(): ?string
    {
        return $this->timing ? (static::TIMINGS[$this->timing] ?? null) : null;
    }

    public function isOnTime(): bool
    {
        return $this->timing === static::TIMING_ON_TIME;
    }

    public function isLate(): bool
    {
        return $this->timing === static::TIMING_LATE;
    }

    /**
     * The billing cell in the workbook, e.g. "Paid Cash". Never invents a
     * method that was not recorded.
     */
    public function paymentLabel(): ?string
    {
        if ($this->task_type !== 'payment' || ! $this->payment_method) {
            return null;
        }

        return static::PAYMENT_METHODS[$this->payment_method] ?? 'Paid';
    }

    public function paymentDetail(): ?string
    {
        $label = $this->paymentLabel();

        if (! $label) {
            return null;
        }

        return $this->payment_at
            ? $label.' — '.$this->payment_at->format('M j, Y g:i A')
            : $label;
    }

    /**
     * Derives the business-facing status. A finished task reports its recorded
     * outcome; a still-pending task whose period has ended becomes
     * Missed / Unreturned / Unpaid depending on the task type.
     */
    public function effectiveStatus(): string
    {
        if ($this->isCompleted()) {
            return $this->actual_status;
        }

        if ($this->isInProgress()) {
            return static::ACTUAL_STATUS_IN_PROGRESS;
        }

        if ($this->isPastDue()) {
            return match ($this->task_type) {
                'return' => static::ACTUAL_STATUS_UNRETURNED,
                'payment' => static::ACTUAL_STATUS_UNPAID,
                default => static::ACTUAL_STATUS_MISSED,
            };
        }

        return $this->actual_status ?: static::ACTUAL_STATUS_PENDING;
    }

    public function effectiveStatusLabel(): string
    {
        if ($this->isPaidViaBilling()) {
            return 'Paid (Billing)';
        }

        return static::ACTUAL_STATUSES[$this->effectiveStatus()] ?? ucfirst((string) $this->effectiveStatus());
    }

    /**
     * Compact cell text for the tracker grid, mirroring how the workbook writes
     * the ACTUAL side of a target ("Pending", "On Time", "Paid Cash", ...).
     */
    public function cellLabel(): string
    {
        if ($this->isPaidViaBilling()) {
            return 'Paid (Billing)';
        }

        if ($this->isInProgress()) {
            return 'In Progress';
        }

        if (! $this->isCompleted()) {
            return $this->effectiveStatusLabel();
        }

        if ($this->isLate()) {
            return $this->paymentDetail() ?? 'Late';
        }

        return $this->paymentDetail() ?? $this->effectiveStatusLabel();
    }

    public function assignedStaffDisplayName(): string
    {
        return $this->assignedStaff?->name ?: (string) ($this->assigned_staff_name ?: '');
    }

    public function performedByDisplayName(): string
    {
        return $this->performedBy?->name ?: (string) $this->performed_by_name;
    }

    public function performedByRoleLabel(): string
    {
        $role = $this->performed_by_role ?? $this->performedBy?->role;

        return match ($role) {
            'admin' => 'Admin',
            'supervisor' => 'Supervisor',
            'staff' => 'Staff',
            default => $role ? ucfirst((string) $role) : '—',
        };
    }

    public function hasAttachment(): bool
    {
        return $this->attachment_path !== null;
    }

    public function setPerformer(?User $user = null): void
    {
        if (! $user) {
            return;
        }

        $this->performed_by_id = $user->id;
        $this->performed_by_name = $user->name;
        $this->performed_by_role = $user->role;
    }

    public function startProcessing(?User $user = null): void
    {
        if (! $this->isPending()) {
            throw new InvalidArgumentException(
                "Cannot start a \"{$this->effectiveStatusLabel()}\" task."
            );
        }

        $this->actual_status = static::ACTUAL_STATUS_IN_PROGRESS;
        $this->started_at = now();
        $this->setPerformer($user);
        $this->save();
    }

    /**
     * Closes out the task using the task-specific wording from the comparison
     * workbook (Pick-Up becomes On Time, Record Completed, Return Returned and
     * Billing Paid), then records whether it landed on or after the target date.
     */
    public function complete(?User $user = null, ?string $paymentMethod = null, ?Carbon $paidAt = null): void
    {
        if (! $this->isInProgress()) {
            throw new InvalidArgumentException(
                "Cannot complete a \"{$this->effectiveStatusLabel()}\" task."
            );
        }

        if ($this->requiresAttachment() && ! $this->attachment_path) {
            throw new InvalidArgumentException(
                "{$this->attachmentHint()} is required to complete this task."
            );
        }

        $this->actual_status = $this->expectedStatus();
        $this->ended_at = now();
        $this->setPerformer($user);

        if ($this->task_type === 'payment') {
            $this->payment_method = $paymentMethod;
            $this->payment_at = $paidAt ?? now();
        }

        // Resolve timing after the finish date is on the model.
        $this->timing = $this->resolveTiming();
        $this->save();

        if ($this->bookkeeping) {
            $this->bookkeeping->syncOverallStatus();
        }
    }

    public function requiresAttachment(): bool
    {
        return static::REQUIRES_ATTACHMENT[$this->task_type] ?? true;
    }

    public function durationInMinutes(): ?int
    {
        if (! $this->started_at || ! $this->ended_at) {
            return null;
        }

        return $this->started_at->diffInMinutes($this->ended_at);
    }

    public function durationHuman(): ?string
    {
        $minutes = $this->durationInMinutes();
        if ($minutes === null) {
            return null;
        }

        if ($minutes < 60) {
            return "{$minutes} minute".($minutes === 1 ? '' : 's');
        }

        // Cast before comparing: floor() returns a float, and a strict
        // comparison against 1 would otherwise pluralise "1 hours".
        $hours = (int) floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        $parts = [];
        if ($hours > 0) {
            $parts[] = "{$hours} hour".($hours === 1 ? '' : 's');
        }
        if ($remainingMinutes > 0) {
            $parts[] = "{$remainingMinutes} minute".($remainingMinutes === 1 ? '' : 's');
        }

        return implode(' ', $parts);
    }
}
