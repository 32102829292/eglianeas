<?php

namespace App\Models;

use App\Casts\DateOnlyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class WorkPlanTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'work_date',
        'original_work_date',
        'carried_over_at',
        'carried_over_by',
        'legend_code',
        'legend_name_snapshot',
        'legend_category_snapshot',
        'custom_title',
        'description',
        'details',
        'success_criteria',
        'is_additional',
        'additional_rating',
        'additional_rated_by',
        'additional_rated_at',
        'status',
        'completed_at',
        'completion_notes',
        'returned_reason',
        'evidence_path',
        'reviewed_by',
        'reviewed_at',
        'completed_by',
        'client_name',
        'client_code',
        'client_details',
        'created_by',
        'edited_by',
    ];

    protected function casts(): array
    {
        return [
            'work_date' => DateOnlyCast::class,
            'original_work_date' => DateOnlyCast::class,
            'carried_over_at' => 'datetime',
            'completed_at' => DateOnlyCast::class,
            'reviewed_at' => 'datetime',
            'additional_rated_at' => 'datetime',
            'is_additional' => 'boolean',
            'additional_rating' => 'integer',
            'client_details' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_ON_HOLD = 'on_hold';

    public const STATUS_RETURNED_FOR_REVISION = 'returned_for_revision';

    public const STATUSES = [
        self::STATUS_PENDING => 'Not Started',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
        self::STATUS_ON_HOLD => 'On Hold',
        self::STATUS_RETURNED_FOR_REVISION => 'Returned for Revision',
    ];

    /**
     * Statuses that count against the planned completion score.
     */
    public const INCOMPLETE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_ON_HOLD,
        self::STATUS_RETURNED_FOR_REVISION,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function carrier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'carried_over_by');
    }

    public function additionalRater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'additional_rated_by');
    }

    public function problems(): HasMany
    {
        return $this->hasMany(WorkPlanTaskProblem::class)->latest();
    }

    public function carries(): HasMany
    {
        return $this->hasMany(WorkPlanTaskCarry::class)->latest('created_at');
    }

    public function openProblems(): HasMany
    {
        return $this->hasMany(WorkPlanTaskProblem::class)->where('status', WorkPlanTaskProblem::STATUS_OPEN);
    }

    public function legend(): BelongsTo
    {
        return $this->belongsTo(WorkPlanLegend::class, 'legend_code', 'code');
    }

    public function audits(): HasMany
    {
        return $this->hasMany(WorkPlanAudit::class, 'work_plan_task_id')->latest('changed_at');
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->where('work_date', $date->toDateString());
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForUserAndDate(Builder $query, int $userId, Carbon $date): Builder
    {
        return $query->where('user_id', $userId)
            ->where('work_date', $date->toDateString());
    }

    /**
     * Restrict a query to the records an employee is allowed to see.
     * Admin and supervisor queries are left untouched.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->isAdminOrSupervisor()) {
            return $query;
        }

        return $query->where('user_id', $user->id);
    }

    public function scopePlanned(Builder $query): Builder
    {
        return $query->where('is_additional', false);
    }

    public function scopeAdditional(Builder $query): Builder
    {
        return $query->where('is_additional', true);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function scopeUnfinished(Builder $query): Builder
    {
        return $query->whereIn('status', self::INCOMPLETE_STATUSES);
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('work_date', '<', Carbon::today()->toDateString())
            ->whereIn('status', self::INCOMPLETE_STATUSES);
    }

    public function scopeNonWorking(Builder $query): Builder
    {
        return $query->where('legend_code', 'S');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isOnHold(): bool
    {
        return $this->status === self::STATUS_ON_HOLD;
    }

    public function isAdditional(): bool
    {
        return (bool) $this->is_additional;
    }

    public function isReturnedForRevision(): bool
    {
        return $this->status === self::STATUS_RETURNED_FOR_REVISION;
    }

    public function isUnfinished(): bool
    {
        return in_array($this->status, self::INCOMPLETE_STATUSES, true);
    }

    /**
     * A task is non-working when it uses the S code or its legend is flagged
     * as non-working. Non-working days are excluded from scoring entirely.
     */
    public function isNonWorking(): bool
    {
        if ($this->legend_code === 'S') {
            return true;
        }

        return (bool) ($this->legend?->is_non_working);
    }

    public function requiresDetails(): bool
    {
        if ($this->legend) {
            return (bool) $this->legend->requires_details;
        }

        return in_array($this->legend_code, ['T', 'T8'], true);
    }

    /**
     * The date a task counts towards for scoring and incomplete history.
     * Carried tasks keep their original target date.
     */
    public function effectiveWorkDate(): Carbon
    {
        return $this->original_work_date ?? $this->work_date;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function getLegendLabel(): string
    {
        if ($this->legend_name_snapshot) {
            return $this->legend_code
                ? $this->legend_code.' - '.$this->legend_name_snapshot
                : $this->legend_name_snapshot;
        }

        if ($this->legend) {
            return $this->legend->code.' - '.$this->legend->name;
        }

        return $this->custom_title ?? 'Custom Task';
    }

    public function isOverdue(): bool
    {
        return $this->isUnfinished() && $this->work_date < Carbon::today();
    }
}
