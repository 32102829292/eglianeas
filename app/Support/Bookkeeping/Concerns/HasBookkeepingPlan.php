<?php

namespace App\Support\Bookkeeping\Concerns;

use App\Models\User;
use App\Support\Bookkeeping\BookkeepingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Behaviour shared by the Monthly and Quarterly bookkeeping plans.
 *
 * This is deliberately a copy of the Weekly Bookkeeping rules rather than an
 * extraction out of `WeeklyBookkeeping`: the weekly model stays untouched, and
 * the two new modules agree with each other through here. Any change to the
 * plan semantics therefore belongs in all three places, not silently in one.
 *
 * @property Collection<int, \Illuminate\Database\Eloquent\Model> $targets
 */
trait HasBookkeepingPlan
{
    public function period(): BookkeepingPeriod
    {
        return BookkeepingPeriod::fromDate(static::PERIOD_KIND, $this->{static::PERIOD_START_COLUMN});
    }

    /** "Month" or "Quarter". */
    public function periodUnit(): string
    {
        return 'Month';
    }

    public function periodUnitPlural(): string
    {
        return 'Months';
    }

    /** "Monthly target" / "Quarterly target", used in prose and headings. */
    public function planNoun(): string
    {
        return 'Monthly target';
    }

    public function planNounTitle(): string
    {
        return 'Monthly Target';
    }

    /** Prefix for activity-log action names, e.g. "monthly_bookkeeping". */
    public function activityPrefix(): string
    {
        return 'monthly_bookkeeping';
    }

    /** Route-name prefix, e.g. "admin.monthly-bookkeeping". */
    public function routePrefix(): string
    {
        return 'admin.monthly-bookkeeping';
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
        return $this->status === static::STATUS_COMPLETED;
    }

    public function statusLabel(): string
    {
        return static::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function lastTargetDate(): ?Carbon
    {
        $date = $this->targets()->max('target_date');

        return $date ? Carbon::parse($date) : null;
    }

    public function targetClientCount(): int
    {
        return $this->targets()->distinct('client_id')->count('client_id');
    }

    public function syncOverallStatus(): void
    {
        $targets = $this->targets;

        if ($targets->isEmpty()) {
            $this->status = static::STATUS_NOT_STARTED;
            $this->saveQuietly();

            return;
        }

        $needsAttention = 0;
        foreach ($targets as $target) {
            if (! $target->isCompleted() && $target->isPastDue()) {
                $needsAttention++;
            }
        }

        if ($targets->every(fn ($t) => $t->isCompleted())) {
            $this->status = static::STATUS_COMPLETED;
        } elseif ($needsAttention > 0) {
            $this->status = static::STATUS_DELAYED;
        } elseif ($targets->contains(fn ($t) => $t->isInProgress())) {
            $this->status = static::STATUS_IN_PROGRESS;
        } elseif ($targets->contains(fn ($t) => $t->isCompleted())) {
            $this->status = static::STATUS_IN_PROGRESS;
        } else {
            $this->status = static::STATUS_NOT_STARTED;
        }

        $this->saveQuietly();
    }

    public function completionPercent(): int
    {
        $total = $this->targets->count();
        if ($total === 0) {
            return $this->isDone() ? 100 : 0;
        }

        /*
         * A task is done when it reached its task-specific outcome, not only
         * when it happens to read "completed": Pick-Up closes as On Time,
         * Return as Returned and Billing as Paid.
         */
        $done = $this->targets->filter(fn ($t) => $t->isCompleted())->count();

        return (int) round(($done / $total) * 100);
    }

    /**
     * @return array<string, int>
     */
    public function targetStats(): array
    {
        $targets = $this->targets;

        return [
            'clients' => $targets->pluck('client_id')->unique()->count(),
            'tasks' => $targets->count(),
            'completed' => $targets->filter(fn ($t) => $t->isCompleted())->count(),
            'in_progress' => $targets->filter(fn ($t) => $t->isInProgress())->count(),
            'pending' => $targets->filter(fn ($t) => $t->isPending())->count(),
            'attention' => $targets->filter(fn ($t) => ! $t->isCompleted() && $t->isPastDue())->count(),
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
     * Staff see only the work named to them. Supervisors and admins see the
     * whole period, matching the accountability model in the comparison sheet.
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
}
