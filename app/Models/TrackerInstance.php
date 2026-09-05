<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrackerInstance extends Model
{
    use HasFactory;

    protected $table = 'tracker_instances';

    public const STATUS_TODO = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_TODO => 'To Do',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_ON_HOLD => 'On Hold',
        self::STATUS_DONE => 'Done',
    ];

    protected $fillable = [
        'service_id',
        'client_id',
        'other_service_id',
        'status',
        'on_hold_reason',
        'date_identified',
        'date_started',
        'date_completed',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_identified' => 'date',
            'date_started' => 'date',
            'date_completed' => 'date',
        ];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(TrackerService::class, 'service_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function otherService(): BelongsTo
    {
        return $this->belongsTo(OtherService::class, 'other_service_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(TrackerAssignment::class, 'instance_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'tracker_instance_id')->orderBy('created_at')->orderBy('id');
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isOnHold(): bool
    {
        return $this->status === self::STATUS_ON_HOLD;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function completionPercent(): int
    {
        $total = $this->assignments()->count();
        if ($total === 0) {
            return $this->isDone() ? 100 : 0;
        }

        $done = $this->assignments()->where('completed', true)->count();

        return (int) round(($done / $total) * 100);
    }

    public function syncOverallStatus(): void
    {
        $assignments = $this->assignments()->get();
        if ($assignments->isEmpty()) {
            return;
        }

        $done = $assignments->where('completed', true)->count();
        $total = $assignments->count();

        if ($done >= $total) {
            $this->status = self::STATUS_DONE;
        } elseif ($done > 0) {
            $this->status = self::STATUS_IN_PROGRESS;
        } else {
            $this->status = self::STATUS_TODO;
        }

        $this->saveQuietly();
    }

    public function isAssignedTo(User $user): bool
    {
        return $this->assignments()->where(function ($query) use ($user) {
            $query->where('staff_id', $user->id)
                ->orWhereRaw('LOWER(staff_name) = ?', [mb_strtolower(trim((string) $user->name))]);
        })->exists();
    }

    public function startProcessing(): void
    {
        $this->assertStatus(self::STATUS_TODO);
        $this->status = self::STATUS_IN_PROGRESS;
        $this->date_started = $this->date_started ?? now()->toDateString();
        $this->save();
    }

    public function hold(string $reason): void
    {
        if (trim($reason) === '') {
            throw new \InvalidArgumentException('A reason is required to put a service on hold.');
        }

        $this->assertStatus(self::STATUS_IN_PROGRESS);
        $this->status = self::STATUS_ON_HOLD;
        $this->on_hold_reason = trim($reason);
        $this->save();
    }

    public function resume(): void
    {
        $this->assertStatus(self::STATUS_ON_HOLD);
        $this->status = self::STATUS_IN_PROGRESS;
        $this->on_hold_reason = null;
        $this->save();
    }

    public function complete(): void
    {
        $this->assertStatus(self::STATUS_IN_PROGRESS);
        $this->status = self::STATUS_DONE;
        $this->date_completed = now()->toDateString();
        $this->save();
    }

    private function assertStatus(string $expected): void
    {
        if ($this->status !== $expected) {
            throw new \InvalidArgumentException(
                "Cannot transition a \"{$this->statusLabel()}\" service to the requested state."
            );
        }
    }
}
