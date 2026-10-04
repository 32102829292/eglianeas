<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use App\Casts\DateOnlyCast;

class DailyJournal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'required_date',
        'problems_encountered',
        'achievements',
        'suggested_solutions',
        'evidence_paths',
        'status',
        'submitted_at',
        'reminded_at',
        'reminder_count',
    ];

    protected function casts(): array
    {
        return [
            'required_date' => DateOnlyCast::class,
            'evidence_paths' => 'array',
            'submitted_at' => 'datetime',
            'reminded_at' => 'datetime',
            'reminder_count' => 'integer',
        ];
    }

    public const STATUS_MISSING = 'missing';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_LATE = 'late';
    public const STATUSES = [self::STATUS_MISSING, self::STATUS_SUBMITTED, self::STATUS_LATE];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeForDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereDate('required_date', $date->toDateString());
    }

    public function scopeMissing(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_MISSING);
    }

    public function scopeSubmitted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SUBMITTED);
    }

    public function scopeLate(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_LATE);
    }

    public function scopeNotMissing(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_SUBMITTED, self::STATUS_LATE]);
    }

    public function isMissing(): bool
    {
        return $this->status === self::STATUS_MISSING;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isLate(): bool
    {
        return $this->status === self::STATUS_LATE;
    }

    public function isNotMissing(): bool
    {
        return $this->isSubmitted() || $this->isLate();
    }

    public function getStatusBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED => 'badge-success',
            self::STATUS_LATE => 'badge-warn',
            default => 'badge-danger',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_LATE => 'Late',
            default => 'Missing',
        };
    }

    public function getStatusIcon(): string
    {
        return match ($this->status) {
            self::STATUS_SUBMITTED => '✓',
            self::STATUS_LATE => '⚠',
            default => '⚠',
        };
    }

    public function hasEvidence(): bool
    {
        return ! empty($this->evidence_paths) && is_array($this->evidence_paths);
    }

    public function evidenceCount(): int
    {
        return $this->hasEvidence() ? count($this->evidence_paths) : 0;
    }

    public function markAsSubmitted(?Carbon $submittedAt = null): void
    {
        $isLate = $this->status === self::STATUS_MISSING && $this->required_date->lt(Carbon::today());

        $this->update([
            'status' => $isLate ? self::STATUS_LATE : self::STATUS_SUBMITTED,
            'submitted_at' => $submittedAt ?? Carbon::now(),
        ]);
    }

    public function markAsMissing(): void
    {
        $this->update([
            'status' => self::STATUS_MISSING,
            'submitted_at' => null,
        ]);
    }

    public function incrementReminder(): void
    {
        $this->increment('reminder_count');
        $this->update(['reminded_at' => Carbon::now()]);
    }

    public function wasRemindedRecently(int $cooldownDays = 7): bool
    {
        if (! $this->reminded_at) {
            return false;
        }

        return $this->reminded_at->gt(now()->subDays($cooldownDays));
    }

    public function evidencePaths(): array
    {
        return $this->evidence_paths ?? [];
    }

    public function addEvidencePath(string $path): void
    {
        $paths = $this->evidencePaths();
        $paths[] = $path;
        $this->update(['evidence_paths' => $paths]);
    }

    public static function getOrCreateForUserAndDate(int $userId, Carbon $date): self
    {
        $dateString = $date->toDateString();

        $journal = static::where('user_id', $userId)
            ->whereDate('required_date', $dateString)
            ->first();

        if ($journal) {
            return $journal;
        }

        try {
            return static::create([
                'user_id' => $userId,
                'required_date' => $date->copy()->startOfDay(),
                'status' => self::STATUS_MISSING,
                'problems_encountered' => '',
                'achievements' => '',
                'suggested_solutions' => '',
                'evidence_paths' => [],
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return static::where('user_id', $userId)
                    ->whereDate('required_date', $dateString)
                    ->firstOrFail();
            }
            throw $e;
        }
    }
}