<?php

namespace App\Models;

use App\Support\Bookkeeping\Concerns\HasBookkeepingTargetWorkflow;
use App\Support\Bookkeeping\Concerns\HasBookkeepingTasks;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single client's target work inside a monthly plan. Mirrors
 * `WeeklyBookkeepingTarget` task for task.
 */
class MonthlyBookkeepingTarget extends Model
{
    use HasBookkeepingTargetWorkflow;
    use HasBookkeepingTasks;
    use HasFactory;

    protected $table = 'monthly_bookkeeping_targets';

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
        'monthly_bookkeeping_id',
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

    public function bookkeeping(): BelongsTo
    {
        return $this->belongsTo(MonthlyBookkeeping::class, 'monthly_bookkeeping_id');
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
}
