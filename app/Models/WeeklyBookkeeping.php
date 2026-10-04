<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WeeklyBookkeeping extends Model
{
    use HasFactory;

    protected $table = 'weekly_bookkeeping';

    public const STATUS_NOT_STARTED = 'not_started';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DELAYED = 'delayed';

    public const STATUSES = [
        self::STATUS_NOT_STARTED => 'Not Started',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_COMPLETED => 'Completed',
        self::STATUS_DELAYED => 'Delayed',
    ];

    public const TASK_TYPES = [
        'pickup' => 'Pick-Up',
        'record' => 'Record',
        'return' => 'Return / Collect',
        'payment' => 'Billing / Payment',
    ];

    protected $fillable = [
        'staff_id',
        'client_id',
        'week_start',
        'week_end',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(WeeklyBookkeepingTarget::class, 'weekly_bookkeeping_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'weekly_bookkeeping_id')->orderBy('created_at')->orderBy('id');
    }

    public function displayOwnerName(): string
    {
        return $this->staff?->name ?? '—';
    }

    public function ownerRoleLabel(): string
    {
        if (! $this->staff) {
            return '—';
        }

        return match ($this->staff->role) {
            'supervisor' => 'Supervisor',
            'staff' => 'Staff',
            'admin' => 'Admin',
            default => ucfirst((string) $this->staff->role),
        };
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function lastTargetDate(): ?\Illuminate\Support\Carbon
    {
        return $this->targets()->max('target_date') ? \Illuminate\Support\Carbon::parse($this->targets()->max('target_date')) : null;
    }

    public function targetClientCount(): int
    {
        return $this->targets()->distinct('client_id')->count('client_id');
    }

    public function syncOverallStatus(): void
    {
        $targets = $this->targets;

        if ($targets->isEmpty()) {
            $this->status = self::STATUS_NOT_STARTED;
            $this->saveQuietly();

            return;
        }

        $needsAttention = 0;
        foreach ($targets as $target) {
            if (! $target->isCompleted() && $target->isPastDue()) {
                $needsAttention++;
            }
        }

        if ($targets->every(fn (WeeklyBookkeepingTarget $t) => $t->isCompleted())) {
            $this->status = self::STATUS_COMPLETED;
        } elseif ($needsAttention > 0) {
            $this->status = self::STATUS_DELAYED;
        } elseif ($targets->contains(fn (WeeklyBookkeepingTarget $t) => $t->isInProgress())) {
            $this->status = self::STATUS_IN_PROGRESS;
        } elseif ($targets->contains(fn (WeeklyBookkeepingTarget $t) => $t->isCompleted())) {
            $this->status = self::STATUS_IN_PROGRESS;
        } else {
            $this->status = self::STATUS_NOT_STARTED;
        }

        $this->saveQuietly();
    }

    public function completionPercent(): int
    {
        $total = $this->targets->count();
        if ($total === 0) {
            return $this->isDone() ? 100 : 0;
        }

        $done = $this->targets->where('actual_status', WeeklyBookkeepingTarget::ACTUAL_STATUS_COMPLETED)->count();

        return (int) round(($done / $total) * 100);
    }

    public function targetStats(): array
    {
        $targets = $this->targets;

        return [
            'clients' => $targets->pluck('client_id')->unique()->count(),
            'tasks' => $targets->count(),
            'completed' => $targets->filter(fn (WeeklyBookkeepingTarget $t) => $t->isCompleted())->count(),
            'in_progress' => $targets->filter(fn (WeeklyBookkeepingTarget $t) => $t->isInProgress())->count(),
            'pending' => $targets->filter(fn (WeeklyBookkeepingTarget $t) => $t->isPending())->count(),
            'attention' => $targets->filter(fn (WeeklyBookkeepingTarget $t) => ! $t->isCompleted() && $t->isPastDue())->count(),
        ];
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->staff_id === $user->id;
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->isOwnedBy($user)
            || $this->targets()->where('assigned_staff_id', $user->id)->exists()
            || $this->targets()->where('performed_by_id', $user->id)->exists();
    }

    /**
     * Staff see only the tasks named to them. Supervisors and admins see the
     * entire week, matching the accountability model in the comparison sheet.
     */
    public function scopeVisibleTo($query, User $user)
    {
        if ($user->isAdmin() || $user->isSupervisor()) {
            return $query;
        }

        return $query->where(function ($query) use ($user) {
            $query->where('staff_id', $user->id)
                ->orWhereHas('targets', fn ($t) => $t->where('assigned_staff_id', $user->id))
                ->orWhereHas('targets', fn ($t) => $t->where('performed_by_id', $user->id));
        });
    }

    public static function getWeekDates(int $year, int $week): array
    {
        $weekStart = \Carbon\Carbon::createFromDate($year, 1, 1)
            ->startOfWeek(\Carbon\Carbon::MONDAY)
            ->addWeeks($week - 1);

        return [
            'week_start' => $weekStart->copy(),
            'week_end' => $weekStart->copy()->endOfWeek(\Carbon\Carbon::SUNDAY),
        ];
    }
}