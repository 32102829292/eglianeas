<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A saved (frozen) Cash Daily Position Report.
 *
 * A report captures one client's daily cash position for one accounting period
 * exactly as it stood at save time. Reopening it reads these stored figures and
 * never recalculates from live records, so later changes to positions, payments
 * or bookkeeping targets cannot rewrite history.
 *
 * The live worksheet (cash_daily_positions / cash_daily_lines / cash_payments)
 * is untouched; this is a separate snapshot layer.
 */
class CashDailyReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'period_type',
        'year',
        'month',
        'week',
        'quarter',
        'period_start',
        'period_end',
        'period_key',
        'beginning_balance',
        'budget',
        'total_collected',
        'total_salaries',
        'total_expenses',
        'total_less',
        'total_budget',
        'total_over_short',
        'label',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'year' => 'integer',
            'month' => 'integer',
            'week' => 'integer',
            'quarter' => 'integer',
            'beginning_balance' => 'decimal:2',
            'budget' => 'decimal:2',
            'total_collected' => 'decimal:2',
            'total_salaries' => 'decimal:2',
            'total_expenses' => 'decimal:2',
            'total_less' => 'decimal:2',
            'total_budget' => 'decimal:2',
            'total_over_short' => 'decimal:2',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function days(): HasMany
    {
        return $this->hasMany(CashDailyReportDay::class)->orderBy('report_date');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashDailyReportLine::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(CashDailyReportPayment::class)->orderBy('paid_on');
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function periodTypeLabel(): string
    {
        return \App\Support\Cash\CashReportPeriod::typeLabel($this->period_type);
    }

    /**
     * A stable, server-generated key that uniquely identifies the exact period
     * within a client. Combined with client_id it is the report's uniqueness
     * guarantee, so the same client + period cannot be saved twice.
     */
    public static function periodKey(string $type, int $year, ?int $month = null, ?int $week = null, ?int $quarter = null): string
    {
        return match ($type) {
            \App\Support\Cash\CashReportPeriod::WEEK => "w:{$year}-{$month}-{$week}",
            \App\Support\Cash\CashReportPeriod::MONTH => "m:{$year}-{$month}",
            \App\Support\Cash\CashReportPeriod::QUARTER => "q:{$year}-{$quarter}",
            default => "x:{$year}",
        };
    }

    /**
     * Human heading for the saved report, e.g. "October 2026 · 2nd Week".
     */
    public function periodLabel(): string
    {
        $start = $this->period_start;

        return match ($this->period_type) {
            \App\Support\Cash\CashReportPeriod::WEEK => $start->format('F Y').' · '.self::ordinal($this->week).' Week',
            \App\Support\Cash\CashReportPeriod::MONTH => $start->format('F Y'),
            \App\Support\Cash\CashReportPeriod::QUARTER => 'Q'.$this->quarter.' '.$this->year,
            default => $start->format('M j, Y').' – '.$this->period_end->format('M j, Y'),
        };
    }

    private static function ordinal(?int $number): string
    {
        return match ($number) {
            1 => '1st',
            2 => '2nd',
            3 => '3rd',
            4 => '4th',
            5 => '5th',
            default => (string) $number,
        };
    }
}
