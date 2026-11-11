<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffContributionRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'activity_type',
        'source_type',
        'source_id',
        'evaluation_period_id',
        'points',
        'status',
        'awarded_at',
    ];

    protected function casts(): array
    {
        return [
            'awarded_at' => 'datetime',
        ];
    }

    public const ACTIVITY_KAIZEN_SUBMITTED = 'kaizen_submitted';
    public const ACTIVITY_KAIZEN_IMPLEMENTED = 'kaizen_implemented';
    public const ACTIVITY_ADMIN_CONCERN_COMPLETED = 'admin_concern_completed';

    public const ACTIVITY_TYPES = [
        self::ACTIVITY_KAIZEN_SUBMITTED => 'Kaizen Submission',
        self::ACTIVITY_KAIZEN_IMPLEMENTED => 'Kaizen Implementation',
        self::ACTIVITY_ADMIN_CONCERN_COMPLETED => 'Admin Concern Completion',
    ];

    public const STATUS_AWARDED = 'awarded';
    public const STATUS_REVERSED = 'reversed';

    public const STATUSES = [
        self::STATUS_AWARDED => 'Awarded',
        self::STATUS_REVERSED => 'Reversed',
    ];

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function evaluationPeriod(): BelongsTo
    {
        return $this->belongsTo(StaffEvaluationPeriod::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(StaffContributionAudit::class, 'contribution_id');
    }

    public function scopeForStaff(Builder $query, int $staffId): Builder
    {
        return $query->where('staff_id', $staffId);
    }

    public function scopeAwarded(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AWARDED);
    }

    public function totalPoints(Builder $query, int $staffId): int
    {
        return $query->where('staff_id', $staffId)
            ->where('status', self::STATUS_AWARDED)
            ->sum('points');
    }
}
