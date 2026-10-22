<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One frozen payment line of a saved Cash Daily Position Report. The true
 * paid_on date is preserved (a weekend payment keeps its weekend date even
 * though only weekday rows are stored). `cash_payment_id` links back to the
 * original cash_payments row for audit and is nulled if that row disappears.
 */
class CashDailyReportPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_daily_report_id',
        'cash_daily_report_day_id',
        'cash_payment_id',
        'paid_on',
        'amount',
        'method',
        'status',
        'reference',
    ];

    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
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

    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(CashPayment::class, 'cash_payment_id');
    }

    public function statusLabel(): string
    {
        return CashPayment::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
