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
    public const STATUS_DONE = 'done';

    public const STATUSES = [
        self::STATUS_TODO => 'To Do',
        self::STATUS_IN_PROGRESS => 'In Progress',
        self::STATUS_DONE => 'Done',
    ];

    protected $fillable = [
        'service_id',
        'client_id',
        'status',
        'date_identified',
        'date_started',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'date_identified' => 'date',
            'date_started' => 'date',
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

    public function assignments(): HasMany
    {
        return $this->hasMany(TrackerAssignment::class, 'instance_id');
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
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
}
