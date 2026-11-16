<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WorkPlanWeeklySubmission extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';

    protected $fillable = [
        'user_id',
        'week_start',
        'status',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function forUserWeek(int $userId, Carbon|string $weekStart): ?self
    {
        return self::query()
            ->where('user_id', $userId)
            ->whereDate('week_start', Carbon::parse($weekStart)->startOfWeek(Carbon::MONDAY)->toDateString())
            ->first();
    }
}
