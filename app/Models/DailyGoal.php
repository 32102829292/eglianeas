<?php

namespace App\Models;

use App\Casts\DateOnlyCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Represents a targeted goal assigned to an employee for a specific workday.
 *
 * Goals are set weekly (every Friday, or any day by an admin) for the upcoming
 * workweek. Each goal is tied to a specific target_date so the monitor can
 * show exactly which goals applied on the selected report date.
 *
 * Version history is kept in daily_goal_versions so that editing a goal never
 * silently rewrites historical expectations used in past AI evaluations.
 */
class DailyGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'week_start_date',
        'target_date',
        'title',
        'description',
        'success_criteria',
        'status',
        'created_by',
        'edited_by',
        'previous_values',
        'effective_from',
    ];

    protected function casts(): array
    {
        return [
            'week_start_date' => DateOnlyCast::class,
            'target_date'     => DateOnlyCast::class,
            'effective_from'  => DateOnlyCast::class,
            'previous_values' => 'array',
            'created_at'      => 'datetime',
            'updated_at'      => 'datetime',
        ];
    }

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_ACTIVE   => 'Active',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_CANCELLED => 'Cancelled',
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

    public function versions(): HasMany
    {
        return $this->hasMany(DailyGoalVersion::class, 'daily_goal_id')->latest('changed_at');
    }

    /**
     * Scope: goals for a specific user on a specific target date.
     */
    public function scopeForDate(Builder $query, int $userId, Carbon $date): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('target_date', $date->toDateString())
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('id');
    }

    /**
     * Scope: all active goals in a week for a user.
     */
    public function scopeForWeek(Builder $query, int $userId, Carbon $weekStart): Builder
    {
        return $query
            ->where('user_id', $userId)
            ->where('week_start_date', $weekStart->toDateString())
            ->where('status', self::STATUS_ACTIVE)
            ->orderBy('target_date')
            ->orderBy('id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /**
     * Returns the week (Monday start) that this goal belongs to.
     */
    public function weekLabel(): string
    {
        $start = $this->week_start_date;
        $end = (clone $start)->addDays(4);
        return $start->format('M j') . ' – ' . $end->format('M j, Y');
    }
}
