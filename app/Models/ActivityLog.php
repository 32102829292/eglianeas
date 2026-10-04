<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tracker_instance_id',
        'weekly_bookkeeping_id',
        'monthly_bookkeeping_id',
        'quarterly_bookkeeping_id',
        'action',
        'description',
        'ip_address',
        'user_agent',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(TrackerInstance::class, 'tracker_instance_id');
    }

    public function weeklyBookkeeping(): BelongsTo
    {
        return $this->belongsTo(WeeklyBookkeeping::class, 'weekly_bookkeeping_id');
    }

    public function monthlyBookkeeping(): BelongsTo
    {
        return $this->belongsTo(MonthlyBookkeeping::class, 'monthly_bookkeeping_id');
    }

    public function quarterlyBookkeeping(): BelongsTo
    {
        return $this->belongsTo(QuarterlyBookkeeping::class, 'quarterly_bookkeeping_id');
    }

    /**
     * Exactly one of the bookkeeping links is ever populated, so the bookkeeping
     * modules pass their own as a named argument.
     */
    public static function record(
        ?User $user,
        string $action,
        ?string $description = null,
        ?TrackerInstance $instance = null,
        ?WeeklyBookkeeping $bookkeeping = null,
        ?MonthlyBookkeeping $monthlyBookkeeping = null,
        ?QuarterlyBookkeeping $quarterlyBookkeeping = null,
    ): void {
        static::create([
            'user_id' => $user?->id,
            'tracker_instance_id' => $instance?->id,
            'weekly_bookkeeping_id' => $bookkeeping?->id,
            'monthly_bookkeeping_id' => $monthlyBookkeeping?->id,
            'quarterly_bookkeeping_id' => $quarterlyBookkeeping?->id,
            'action' => $action,
            'description' => $description,
            'ip_address' => request()->ip(),
            'user_agent' => mb_substr(request()->userAgent() ?? '', 0, 255),
        ]);
    }
}
