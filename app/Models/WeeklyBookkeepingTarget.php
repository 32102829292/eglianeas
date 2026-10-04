<?php

namespace App\Models;

use App\Support\Bookkeeping\Concerns\HasBookkeepingTargetWorkflow;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WeeklyBookkeepingTarget extends Model
{
    use HasBookkeepingTargetWorkflow;
    use HasFactory;

    protected $table = 'weekly_bookkeeping_targets';

    public const TASK_TYPES = [
        'pickup' => 'Pick-Up',
        'record' => 'Record',
        'return' => 'Return / Collect',
        'payment' => 'Billing / Payment',
    ];

    public const ACTUAL_STATUS_PENDING = 'pending';
    public const ACTUAL_STATUS_IN_PROGRESS = 'in_progress';
    public const ACTUAL_STATUS_ON_TIME = 'on_time';
    public const ACTUAL_STATUS_COMPLETED = 'completed';
    public const ACTUAL_STATUS_RETURNED = 'returned';
    public const ACTUAL_STATUS_PAID = 'paid';
    public const ACTUAL_STATUS_UNRETURNED = 'unreturned';
    public const ACTUAL_STATUS_UNPAID = 'unpaid';
    public const ACTUAL_STATUS_MISSED = 'missed';

    /**
     * Terminology taken from the comparison workbook. The workbook tracks the
     * ACTUAL side of each target, so the labels match what the sheet shows in
     * its Pick-Up / Record / Return / Billing columns.
     */
    public const ACTUAL_STATUSES = [
        self::ACTUAL_STATUS_PENDING => 'Pending',
        self::ACTUAL_STATUS_IN_PROGRESS => 'In Progress',
        self::ACTUAL_STATUS_ON_TIME => 'On Time',
        self::ACTUAL_STATUS_COMPLETED => 'Completed',
        self::ACTUAL_STATUS_RETURNED => 'Returned',
        self::ACTUAL_STATUS_PAID => 'Paid',
        self::ACTUAL_STATUS_UNRETURNED => 'Unreturned',
        self::ACTUAL_STATUS_UNPAID => 'Unpaid',
        self::ACTUAL_STATUS_MISSED => 'Missed',
    ];

    /**
     * The outcome each task type is expected to reach. Anything outside this
     * list is unfinished work, which is what the workbook's
     * "Uncollected / Unfinished" column reports.
     */
    public const SATISFIED_STATUSES = [
        'pickup' => [self::ACTUAL_STATUS_ON_TIME, self::ACTUAL_STATUS_COMPLETED],
        'record' => [self::ACTUAL_STATUS_COMPLETED, self::ACTUAL_STATUS_ON_TIME],
        'return' => [self::ACTUAL_STATUS_RETURNED, self::ACTUAL_STATUS_COMPLETED],
        'payment' => [self::ACTUAL_STATUS_PAID, self::ACTUAL_STATUS_COMPLETED],
    ];

    public const PAYMENT_METHODS = [
        'cash' => 'Paid Cash',
        'gcash' => 'Paid GCash',
        'other' => 'Paid (Other)',
    ];

    public const TIMING_ON_TIME = 'on_time';
    public const TIMING_LATE = 'late';

    public const TIMINGS = [
        self::TIMING_ON_TIME => 'On Time',
        self::TIMING_LATE => 'Completed Late',
    ];

    public const REQUIRES_ATTACHMENT = [
        'pickup' => true,
        'record' => true,
        'return' => true,
        'payment' => true,
    ];

    public const ATTACHMENT_HINTS = [
        'pickup' => 'Proof of pick-up',
        'record' => 'Bookkeeping output / screenshot / file',
        'return' => 'Proof of return',
        'payment' => 'Billing proof / receipt',
    ];

    protected $fillable = [
        'weekly_bookkeeping_id',
        'client_id',
        'task_type',
        'assigned_staff_id',
        'assigned_staff_name',
        'target_date',
        'target_date_auto',
        'actual_status',
        'timing',
        'performed_by_id',
        'performed_by_name',
        'performed_by_role',
        'started_at',
        'ended_at',
        'payment_method',
        'payment_at',
        'payment_status',
        'balance_amount',
        'balance_note',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'target_date_auto' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'payment_at' => 'datetime',
            'balance_amount' => 'decimal:2',
        ];
    }

    public function weeklyBookkeeping(): BelongsTo
    {
        return $this->belongsTo(WeeklyBookkeeping::class, 'weekly_bookkeeping_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_id');
    }

    /**
     * Staff who may legitimately act on this task: the assignee, or any
     * supervisor/admin. The comparison workbook relies on named ownership per
     * task, so a task assigned to someone else is not visible as their work.
     */
    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_staff_id === $user->id;
    }

    public function displayClientName(): string
    {
        return $this->client?->business_name ?: $this->client?->name ?: 'Client #'.$this->client_id;
    }

    public function taskLabel(): string
    {
        return self::TASK_TYPES[$this->task_type] ?? ucfirst((string) $this->task_type);
    }

    public function attachmentHint(): string
    {
        return self::ATTACHMENT_HINTS[$this->task_type] ?? 'Attachment';
    }

    /**
     * The outcome this task is expected to reach, based on the comparison
     * workbook: Pick-Up resolves to On Time, Record to Completed, Return to
     * Returned and Billing to Paid.
     */
    public function expectedStatus(): string
    {
        return match ($this->task_type) {
            'pickup' => self::ACTUAL_STATUS_ON_TIME,
            'record' => self::ACTUAL_STATUS_COMPLETED,
            'return' => self::ACTUAL_STATUS_RETURNED,
            'payment' => self::ACTUAL_STATUS_PAID,
            default => self::ACTUAL_STATUS_COMPLETED,
        };
    }

    public function satisfiedStatuses(): array
    {
        return self::SATISFIED_STATUSES[$this->task_type] ?? [self::ACTUAL_STATUS_COMPLETED];
    }

    /**
     * True once the task reached any of its accepted outcomes. This stays the
     * single "the work is done" predicate so existing status roll-ups keep
     * working regardless of which task-specific wording was recorded.
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
        return $this->actual_status === self::ACTUAL_STATUS_IN_PROGRESS;
    }

    public function isPending(): bool
    {
        return $this->actual_status === self::ACTUAL_STATUS_PENDING;
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
     * The client's paid billing covering this target, when one exists. Bookkeeping
     * never invents payment status; it only reads what Billing already recorded.
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
     * largely overlapping id sets. This loads every involved user once and
     * attaches each target its relations, preserving Laravel's null-relation
     * behaviour for missing or soft-deleted users.
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
     * payment target, which issued one Billing query per target. This fetches
     * every candidate billing for all involved clients in one round trip and
     * hands each target its answer, so the grid renders without extra queries.
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

    public function isPastDue(): bool
    {
        if ($this->isCompleted()) {
            return false;
        }

        $weekEnd = $this->weeklyBookkeeping?->week_end;

        return $weekEnd !== null && now()->gt($weekEnd);
    }

    /**
     * The moment the task was actually finished, which differs per task:
     * payment records when the money was received, everything else when the
     * work was closed out.
     */
    public function actualFinishedAt(): ?\Illuminate\Support\Carbon
    {
        return $this->task_type === 'payment'
            ? ($this->payment_at ?? $this->ended_at)
            : $this->ended_at;
    }

    /**
     * Target vs actual. Compares the actual finish against the target date so
     * the tracker can distinguish "On Time" from "Completed Late" instead of
     * collapsing both into a generic Completed.
     */
    public function resolveTiming(): ?string
    {
        $finishedAt = $this->actualFinishedAt();

        if (! $this->isCompleted() || ! $finishedAt || ! $this->target_date) {
            return null;
        }

        return $finishedAt->startOfDay()->lte($this->target_date->copy()->startOfDay())
            ? self::TIMING_ON_TIME
            : self::TIMING_LATE;
    }

    public function timingLabel(): ?string
    {
        return $this->timing ? (self::TIMINGS[$this->timing] ?? null) : null;
    }

    public function isOnTime(): bool
    {
        return $this->timing === self::TIMING_ON_TIME;
    }

    public function isLate(): bool
    {
        return $this->timing === self::TIMING_LATE;
    }

    /**
     * The billing cell in the workbook, e.g. "Paid Cash" plus the date the
     * payment was received. Never invents a method that was not recorded.
     */
    public function paymentLabel(): ?string
    {
        if ($this->task_type !== 'payment' || ! $this->payment_method) {
            return null;
        }

        return self::PAYMENT_METHODS[$this->payment_method] ?? 'Paid';
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
     * outcome; a still-pending task whose week has ended becomes
     * Missed / Unreturned / Unpaid depending on the task type.
     */
    public function effectiveStatus(): string
    {
        if ($this->isCompleted()) {
            return $this->actual_status;
        }

        if ($this->isInProgress()) {
            return self::ACTUAL_STATUS_IN_PROGRESS;
        }

        if ($this->isPastDue()) {
            return match ($this->task_type) {
                'return' => self::ACTUAL_STATUS_UNRETURNED,
                'payment' => self::ACTUAL_STATUS_UNPAID,
                default => self::ACTUAL_STATUS_MISSED,
            };
        }

        return $this->actual_status ?: self::ACTUAL_STATUS_PENDING;
    }

    public function effectiveStatusLabel(): string
    {
        if ($this->isPaidViaBilling()) {
            return 'Paid (Billing)';
        }

        return self::ACTUAL_STATUSES[$this->effectiveStatus()] ?? ucfirst((string) $this->effectiveStatus());
    }

    /**
     * Compact cell text for the weekly grid, mirroring how the workbook writes
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
            throw new \InvalidArgumentException(
                "Cannot start a \"{$this->effectiveStatusLabel()}\" task."
            );
        }

        $this->actual_status = self::ACTUAL_STATUS_IN_PROGRESS;
        $this->started_at = now();
        $this->setPerformer($user);
        $this->save();
    }

    /**
     * Closes out the task using the task-specific wording from the comparison
     * workbook (Pick-Up becomes On Time, Record Completed, Return Returned and
     * Billing Paid), then records whether it landed on or after the target date.
     */
    public function complete(?User $user = null, ?string $paymentMethod = null, ?\Illuminate\Support\Carbon $paidAt = null): void
    {
        if (! $this->isInProgress()) {
            throw new \InvalidArgumentException(
                "Cannot complete a \"{$this->effectiveStatusLabel()}\" task."
            );
        }

        if ($this->requiresAttachment() && ! $this->attachment_path) {
            throw new \InvalidArgumentException(
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

        if ($this->weeklyBookkeeping) {
            $this->weeklyBookkeeping->syncOverallStatus();
        }
    }

    public function requiresAttachment(): bool
    {
        return self::REQUIRES_ATTACHMENT[$this->task_type] ?? true;
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

        $hours = floor($minutes / 60);
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