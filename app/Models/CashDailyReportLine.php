<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One frozen salary / expense deduction of a saved Cash Daily Position Report.
 * `source_line_id` is a soft audit reference to the original cash_daily_lines
 * row and is deliberately not a foreign key, so live edits never touch it.
 */
class CashDailyReportLine extends Model
{
    use HasFactory;

    public const TYPE_SALARY = CashDailyLine::TYPE_SALARY;

    public const TYPE_EXPENSE = CashDailyLine::TYPE_EXPENSE;

    public const TYPE_ADDITION = CashDailyLine::TYPE_ADDITION;

    protected $fillable = [
        'cash_daily_report_id',
        'cash_daily_report_day_id',
        'type',
        'category',
        'label',
        'amount',
        'notes',
        'source_line_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(CashDailyReport::class, 'cash_daily_report_id');
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(CashDailyReportDay::class, 'cash_daily_report_day_id');
    }

    public function typeLabel(): string
    {
        return CashDailyLine::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
