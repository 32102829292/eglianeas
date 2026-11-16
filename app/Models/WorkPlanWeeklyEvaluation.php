<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WorkPlanWeeklyEvaluation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'week_start',
        'problem_solving_score',
        'notes',
        'evaluated_by',
        'evaluated_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'problem_solving_score' => 'integer',
            'evaluated_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The only values an Admin or Supervisor may award for problem solving.
     * A missing evaluation always scores zero — it is never inferred from
     * problem text or from any problem being marked resolved.
     */
    public const ALLOWED_SCORES = [10, 7, 5, 0];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function evaluator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'evaluated_by');
    }

    public static function forUserWeek(int $userId, Carbon|string $weekStart): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->whereDate('week_start', Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY)->toDateString())
            ->first();
    }
}
