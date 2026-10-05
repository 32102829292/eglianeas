<?php

namespace App\Models;

use App\Support\Bookkeeping\BookkeepingPeriod;
use App\Support\Bookkeeping\Concerns\HasBookkeepingPlan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One calendar quarter's bookkeeping plan.
 *
 * Mirrors `WeeklyBookkeeping` — same statuses, same task types, same
 * accountability rules — with the quarter as the planning unit.
 */
class QuarterlyBookkeeping extends Model
{
    use HasBookkeepingPlan;
    use HasFactory;

    protected $table = 'quarterly_bookkeeping';

    /* Period wiring read by App\Support\Bookkeeping and the shared controller
       trait, so one declaration drives the schema, the routes and the views. */
    public const PERIOD_KIND = BookkeepingPeriod::QUARTERLY;
    public const PERIOD_START_COLUMN = 'quarter_start';
    public const PERIOD_END_COLUMN = 'quarter_end';
    public const PERIOD_START_FIELD = 'quarter_start';
    public const FOREIGN_KEY = 'quarterly_bookkeeping_id';
    public const ACTIVITY_LINK = 'quarterlyBookkeeping';
    public const STORAGE_PREFIX = 'quarterly-bookkeeping';
    public const ROUTE_PREFIX = 'admin.quarterly-bookkeeping';
    public const ACTIVITY_PREFIX = 'quarterly_bookkeeping';

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
        'quarter_start',
        'quarter_end',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'quarter_start' => 'date',
            'quarter_end' => 'date',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function targets(): HasMany
    {
        return $this->hasMany(QuarterlyBookkeepingTarget::class, 'quarterly_bookkeeping_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(ActivityLog::class, 'quarterly_bookkeeping_id')->orderBy('created_at')->orderBy('id');
    }

    public function periodUnit(): string
    {
        return 'Quarter';
    }

    public function periodUnitPlural(): string
    {
        return 'Quarters';
    }

    public function planNoun(): string
    {
        return 'Quarterly target';
    }

    public function planNounTitle(): string
    {
        return 'Quarterly Target';
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
