<?php

namespace App\Models;

use App\Support\Bookkeeping\BookkeepingPeriod;
use App\Support\Bookkeeping\Concerns\HasBookkeepingPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One calendar month's bookkeeping plan.
 *
 * Mirrors `WeeklyBookkeeping` — same statuses, same task types, same
 * accountability rules — with the month as the planning unit.
 */
class MonthlyBookkeeping extends Model
{
    use HasBookkeepingPlan;
    use HasFactory;

    protected $table = 'monthly_bookkeeping';

    /* Period wiring read by App\Support\Bookkeeping and the shared controller
       trait, so one declaration drives the schema, the routes and the views. */
    public const PERIOD_KIND = BookkeepingPeriod::MONTHLY;
    public const PERIOD_START_COLUMN = 'month_start';
    public const PERIOD_END_COLUMN = 'month_end';
    public const PERIOD_START_FIELD = 'month_start';
    public const FOREIGN_KEY = 'monthly_bookkeeping_id';
    public const ACTIVITY_LINK = 'monthlyBookkeeping';
    public const STORAGE_PREFIX = 'monthly-bookkeeping';
    public const ROUTE_PREFIX = 'admin.monthly-bookkeeping';
    public const ACTIVITY_PREFIX = 'monthly_bookkeeping';

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
        'month_start',
        'month_end',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'month_start' => 'date',
            'month_end' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(MonthlyBookkeepingTarget::class, 'monthly_bookkeeping_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'monthly_bookkeeping_id')->orderBy('created_at')->orderBy('id');
    }

    public function periodUnit(): string
    {
        return 'Month';
    }

    public function periodUnitPlural(): string
    {
        return 'Months';
    }

    public function planNoun(): string
    {
        return 'Monthly target';
    }

    public function planNounTitle(): string
    {
        return 'Monthly Target';
    }

    public function activityPrefix(): string
    {
        return self::ACTIVITY_PREFIX;
    }

    public function routePrefix(): string
    {
        return self::ROUTE_PREFIX;
    }
}
