<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One frozen weekday row of a saved Cash Daily Position Report. Every figure
 * here was copied from the live worksheet at save time and is never
 * recalculated when the report is reopened.
 */
class CashDailyReportDay extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_daily_report_id',
        'report_date',
        'beginning_balance',
        'collected',
        'budget',
        'salaries',
        'expenses',
        'less',
        'ending_balance',
        'actual_cash',
        'over_short',
        'auto_beginning',
        'has_weekend_fold',
    ];

    protected function casts(): array
    {
        return [
            'report_date' => 'date',
            'beginning_balance' => 'decimal:2',
            'collected' => 'decimal:2',
            'budget' => 'decimal:2',
            'salaries' => 'decimal:2',
            'expenses' => 'decimal:2',
            'less' => 'decimal:2',
            'ending_balance' => 'decimal:2',
            'actual_cash' => 'decimal:2',
            'over_short' => 'decimal:2',
            'auto_beginning' => 'boolean',
            'has_weekend_fold' => 'boolean',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CashDailyReport::class, 'cash_daily_report_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashDailyReportLine::class, 'cash_daily_report_day_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CashDailyReportPayment::class, 'cash_daily_report_day_id')->orderBy('paid_on');
    }
}
