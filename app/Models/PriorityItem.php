<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PriorityItem extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPE_PRIORITY_TASK = 'priority_task';

    public const TYPE_TODO = 'todo';

    public const TYPE_LESSON_LEARNED = 'lesson_learned';

    public const TYPES = [
        self::TYPE_PRIORITY_TASK => 'Priority Task',
        self::TYPE_TODO => 'To-Do',
        self::TYPE_LESSON_LEARNED => 'Lesson Learned',
    ];

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_URGENT = 'urgent';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Low',
        self::PRIORITY_MEDIUM => 'Medium',
        self::PRIORITY_HIGH => 'High',
        self::PRIORITY_URGENT => 'Urgent',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_OVERDUE => 'Overdue',
    ];

    /**
     * Default number of days from creation until an item is due, keyed by
     * priority. Urgent is due the same day, High the next day, Medium within
     * three days and Low within one week.
     */
    public const DEFAULT_DUE_DAYS = [
        self::PRIORITY_URGENT => 0,
        self::PRIORITY_HIGH => 1,
        self::PRIORITY_MEDIUM => 3,
        self::PRIORITY_LOW => 7,
    ];

    /**
     * Derived urgency keys (not stored). Urgency is computed from status,
     * deadline and priority so it never drifts from the underlying record.
     */
    public const URGENCY_ACTION = 'urgent_action';

    public const URGENCY_OVERDUE = 'overdue';

    public const URGENCY_DUE_TODAY = 'due_today';

    public const URGENCY_DUE_TOMORROW = 'due_tomorrow';

    public const URGENCY_DUE_SOON = 'due_soon';

    public const URGENCY_ON_TRACK = 'on_track';

    public const URGENCY_COMPLETED = 'completed';

    public const URGENCIES = [
        self::URGENCY_ACTION => 'Urgent / Action Now',
        self::URGENCY_OVERDUE => 'Overdue',
        self::URGENCY_DUE_TODAY => 'Due Today',
        self::URGENCY_DUE_TOMORROW => 'Due Tomorrow',
        self::URGENCY_DUE_SOON => 'Due Soon',
        self::URGENCY_ON_TRACK => 'On Track',
        self::URGENCY_COMPLETED => 'Completed',
    ];

    protected $fillable = [
        'task_lesson',
        'type',
        'description',
        'priority',
        'assigned_staff_id',
        'due_date',
        'status',
        'notes',
        'evidence_path',
        'evidence_name',
        'evidence_mime',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
        ];
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function checklistItems(): MorphMany
    {
        return $this->morphMany(ChecklistItem::class, 'checklistable');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function typeBadgeClass(): string
    {
        return match ($this->type) {
            self::TYPE_PRIORITY_TASK => 'badge-danger',
            self::TYPE_TODO => 'badge-info',
            self::TYPE_LESSON_LEARNED => 'badge-success',
            default => 'badge-neutral',
        };
    }

    public function priorityLabel(): string
    {
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }

    public function priorityBadgeClass(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'badge-danger',
            self::PRIORITY_HIGH => 'badge-warn',
            self::PRIORITY_MEDIUM => 'badge-info',
            default => 'badge-success',
        };
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_COMPLETED => 'badge-success',
            self::STATUS_IN_PROGRESS => 'badge-info',
            self::STATUS_OVERDUE => 'badge-danger',
            default => 'badge-warn',
        };
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assigned_staff_id === $user->id;
    }

    public function isUnassigned(): bool
    {
        return $this->assigned_staff_id === null;
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ($this->isAssignedTo($user)) {
            return true;
        }

        if ($this->isUnassigned() && $user->isOperational()) {
            return true;
        }

        return false;
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            $q->where('assigned_staff_id', $user->id)
                ->orWhere(function ($q2) {
                    $q2->whereNull('assigned_staff_id')
                        ->whereIn('status', [self::STATUS_PENDING, self::STATUS_IN_PROGRESS]);
                });
        });
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(PriorityEvidence::class, 'priority_item_id')->orderBy('created_at', 'desc');
    }

    public function hasEvidence(): bool
    {
        return $this->evidences()->exists() || $this->evidence_path !== null;
    }

    /**
     * Resolve the default due date for a priority, measured from $from
     * (defaults to "now" in the configured application timezone). Returns null
     * for an unknown priority so callers never invent a deadline.
     */
    public static function defaultDueDateForPriority(?string $priority, ?Carbon $from = null): ?Carbon
    {
        if ($priority === null || ! array_key_exists($priority, self::DEFAULT_DUE_DAYS)) {
            return null;
        }

        $from = $from ? $from->copy() : now();

        return $from->startOfDay()->addDays(self::DEFAULT_DUE_DAYS[$priority]);
    }

    /**
     * Workflow ordering driven by derived urgency: overdue first, then the
     * urgent / due-today band, then due tomorrow, due soon, on track and
     * finally completed. Priority is only a tie-breaker within a band, so the
     * stored priority value is never rewritten just because a task is late.
     */
    public function scopeOrderByUrgency(Builder $query): Builder
    {
        $today = now()->startOfDay()->toDateString();
        $tomorrow = now()->startOfDay()->addDay()->toDateString();
        $soon = now()->startOfDay()->addDays(3)->toDateString();

        $rank = 'CASE'
            ." WHEN status = '".self::STATUS_COMPLETED."' THEN 6"
            ." WHEN due_date IS NOT NULL AND due_date < '{$today}' THEN 1"
            ." WHEN priority = '".self::PRIORITY_URGENT."' THEN 2"
            ." WHEN due_date = '{$today}' THEN 2"
            ." WHEN due_date = '{$tomorrow}' THEN 3"
            ." WHEN due_date IS NOT NULL AND due_date > '{$tomorrow}' AND due_date <= '{$soon}' THEN 4"
            .' ELSE 5 END';

        $priorityRank = "CASE priority WHEN '".self::PRIORITY_URGENT."' THEN 0"
            ." WHEN '".self::PRIORITY_HIGH."' THEN 1"
            ." WHEN '".self::PRIORITY_MEDIUM."' THEN 2"
            ." WHEN '".self::PRIORITY_LOW."' THEN 3 ELSE 4 END";

        return $query
            ->orderByRaw($rank)
            ->orderByRaw($priorityRank)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderByDesc('created_at');
    }

    /**
     * Server-side filter for the derived urgency. Mirrors urgencyKey() exactly
     * so the filter never disagrees with the badge shown on the row.
     */
    public function scopeWhereUrgency(Builder $query, string $urgency): Builder
    {
        $today = now()->startOfDay()->toDateString();
        $tomorrow = now()->startOfDay()->addDay()->toDateString();
        $soon = now()->startOfDay()->addDays(3)->toDateString();

        return match ($urgency) {
            self::URGENCY_COMPLETED => $query->where('status', self::STATUS_COMPLETED),

            self::URGENCY_OVERDUE => $query
                ->where('status', '!=', self::STATUS_COMPLETED)
                ->whereNotNull('due_date')
                ->whereDate('due_date', '<', $today),

            self::URGENCY_ACTION => $query
                ->where('status', '!=', self::STATUS_COMPLETED)
                ->where('priority', self::PRIORITY_URGENT)
                ->where(function ($q) use ($today) {
                    $q->whereNull('due_date')->orWhereDate('due_date', '>=', $today);
                }),

            self::URGENCY_DUE_TODAY => $query
                ->where('status', '!=', self::STATUS_COMPLETED)
                ->where('priority', '!=', self::PRIORITY_URGENT)
                ->whereDate('due_date', $today),

            self::URGENCY_DUE_TOMORROW => $query
                ->where('status', '!=', self::STATUS_COMPLETED)
                ->where('priority', '!=', self::PRIORITY_URGENT)
                ->whereDate('due_date', $tomorrow),

            self::URGENCY_DUE_SOON => $query
                ->where('status', '!=', self::STATUS_COMPLETED)
                ->where('priority', '!=', self::PRIORITY_URGENT)
                ->whereDate('due_date', '>', $tomorrow)
                ->whereDate('due_date', '<=', $soon),

            self::URGENCY_ON_TRACK => $query
                ->where('status', '!=', self::STATUS_COMPLETED)
                ->where('priority', '!=', self::PRIORITY_URGENT)
                ->where(function ($q) use ($soon) {
                    $q->whereNull('due_date')->orWhereDate('due_date', '>', $soon);
                }),

            default => $query,
        };
    }

    /**
     * A deadline is only "overdue" while the work is still open. Completed
     * items keep their original due date but are never flagged as overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status !== self::STATUS_COMPLETED
            && $this->due_date !== null
            && $this->due_date->copy()->startOfDay()->lt(now()->startOfDay());
    }

    public function urgencyInstruction(): string
    {
        return match ($this->priority) {
            self::PRIORITY_URGENT => 'URGENT — ACTION REQUIRED NOW',
            self::PRIORITY_HIGH => 'HIGH — ACTION NOW / DUE TOMORROW',
            self::PRIORITY_MEDIUM => 'MEDIUM — COMPLETE WITHIN 3 DAYS',
            self::PRIORITY_LOW => 'LOW — COMPLETE WITHIN 1 WEEK',
            default => '',
        };
    }

    /**
     * Derived urgency key. Single source of truth shared by the badge, the
     * default list ordering and the server-side filter, so urgency can never
     * drift from the underlying status / deadline / priority data.
     */
    public function urgencyKey(): string
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return self::URGENCY_COMPLETED;
        }

        $today = now()->startOfDay();

        if ($this->due_date !== null && $this->due_date->copy()->startOfDay()->lt($today)) {
            return self::URGENCY_OVERDUE;
        }

        if ($this->priority === self::PRIORITY_URGENT) {
            return self::URGENCY_ACTION;
        }

        if ($this->due_date === null) {
            return self::URGENCY_ON_TRACK;
        }

        $days = (int) $today->diffInDays($this->due_date->copy()->startOfDay());

        return match (true) {
            $days === 0 => self::URGENCY_DUE_TODAY,
            $days === 1 => self::URGENCY_DUE_TOMORROW,
            $days <= 3 => self::URGENCY_DUE_SOON,
            default => self::URGENCY_ON_TRACK,
        };
    }

    public function urgencyLabel(): string
    {
        return match ($this->urgencyKey()) {
            self::URGENCY_COMPLETED => 'COMPLETED',
            self::URGENCY_OVERDUE => 'OVERDUE',
            self::URGENCY_ACTION => 'URGENT — ACTION NOW',
            self::URGENCY_DUE_TODAY => 'DUE TODAY',
            self::URGENCY_DUE_TOMORROW => 'DUE TOMORROW',
            self::URGENCY_DUE_SOON => 'DUE SOON',
            default => 'ON TRACK',
        };
    }

    public function urgencyBadgeClass(): string
    {
        return match ($this->urgencyKey()) {
            self::URGENCY_COMPLETED => 'badge-success',
            self::URGENCY_OVERDUE => 'badge-danger',
            self::URGENCY_ACTION => 'badge-urgent',
            self::URGENCY_DUE_TODAY => 'badge-warn',
            self::URGENCY_DUE_TOMORROW,
            self::URGENCY_DUE_SOON => 'badge-info',
            default => 'badge-neutral',
        };
    }

    /**
     * Human deadline label relative to today, e.g. "Due today", "Due in 3
     * days", "Overdue by 2 days" or "Completed". Completed items are never
     * described as overdue.
     */
    public function deadlineLabel(): string
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return 'COMPLETED';
        }

        if ($this->due_date === null) {
            return 'NO DEADLINE';
        }

        $today = now()->startOfDay();
        $due = $this->due_date->copy()->startOfDay();

        if ($due->lt($today)) {
            $days = (int) $due->diffInDays($today);

            return 'OVERDUE BY '.$days.' '.Str::upper(Str::plural('day', $days));
        }

        if ($due->isSameDay($today)) {
            return 'DUE TODAY';
        }

        $days = (int) $today->diffInDays($due);

        return $days === 1 ? 'DUE TOMORROW' : 'DUE IN '.$days.' DAYS';
    }

    public function deadlineBadgeClass(): string
    {
        if ($this->status === self::STATUS_COMPLETED) {
            return 'badge-success';
        }

        if ($this->due_date === null) {
            return 'badge-neutral';
        }

        if ($this->isOverdue()) {
            return 'badge-danger';
        }

        return (int) now()->startOfDay()->diffInDays($this->due_date->copy()->startOfDay()) <= 1
            ? 'badge-warn'
            : 'badge-info';
    }

    /**
     * Admin and Supervisor roles may manage the checklist structure. Staff may
     * only complete items. Visibility is still required, so a user who cannot
     * see the item cannot manage its checklist.
     */
    public function canManageChecklist(User $user): bool
    {
        return ($user->isAdmin() || $user->isSupervisor()) && $this->isVisibleTo($user);
    }
}
