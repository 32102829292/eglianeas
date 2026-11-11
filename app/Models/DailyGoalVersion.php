<?php

namespace App\Models;

use App\Casts\DateOnlyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Records an immutable snapshot of a DailyGoal before each edit, so that
 * editing a goal never silently rewrites historical expectations used in past
 * AI evaluations.
 */
class DailyGoalVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'daily_goal_id',
        'previous_values',
        'new_values',
        'change_reason',
        'changed_by',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'new_values'      => 'array',
            'changed_at'      => 'datetime',
        ];
    }

    public function goal(): BelongsTo
    {
        return $this->belongsTo(DailyGoal::class, 'daily_goal_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
