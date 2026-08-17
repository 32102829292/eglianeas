<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Billing extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_UNPAID => 'Unpaid',
        self::STATUS_OVERDUE => 'Overdue',
        self::STATUS_PAID => 'Paid',
    ];

    public const QUARTERS = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];

    protected $fillable = [
        'client_id',
        'period_label',
        'quarter',
        'year',
        'due_date',
        'sales',
        'rate_2551q',
        'tax_2551q',
        'tax_1701q',
        'cash_in',
        'fee_2551q',
        'fee_1701q',
        'fee_bookkeeping',
        'total',
        'status',
        'paid_at',
        'sales_submitted_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quarter' => 'integer',
            'year' => 'integer',
            'due_date' => 'date',
            'sales' => 'float',
            'rate_2551q' => 'float',
            'tax_2551q' => 'float',
            'tax_1701q' => 'float',
            'cash_in' => 'float',
            'fee_2551q' => 'float',
            'fee_1701q' => 'float',
            'fee_bookkeeping' => 'float',
            'total' => 'float',
            'paid_at' => 'datetime',
            'sales_submitted_at' => 'datetime',
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

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }

    public function hasSubmittedSales(): bool
    {
        return $this->sales_submitted_at !== null;
    }

    /**
     * Default due date for a quarter: end of the quarter's closing month.
     */
    public static function defaultDueDate(?int $quarter, ?int $year): Carbon
    {
        return Carbon::create($year ?: self::currentYear(), max(1, (int) $quarter) * 3, 1)->endOfMonth();
    }

    /**
     * Recompute the stored status from the billing's actual state.
     * Pending = the client has not submitted their sales yet; Paid is
     * only ever set through the payment flow and is never overridden.
     */
    public function syncStatus(): void
    {
        if ($this->isPaid()) {
            return;
        }

        if (! $this->hasSubmittedSales()) {
            $this->status = self::STATUS_PENDING;

            return;
        }

        $this->status = $this->isOverdue() ? self::STATUS_OVERDUE : self::STATUS_UNPAID;
    }

    public static function currentYear(): int
    {
        return (int) now()->format('Y');
    }

    public static function currentQuarter(): int
    {
        return (int) ceil(((int) now()->format('n')) / 3);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function periodTitle(): string
    {
        if ($this->year && $this->quarter) {
            return self::QUARTERS[$this->quarter].' Quarter '.$this->year;
        }

        return $this->period_label ?: 'Billing statement';
    }

    public function periodTitleUppercase(): string
    {
        return strtoupper($this->periodTitle());
    }

    public function money(?float $value): string
    {
        return '₱'.number_format($value ?? 0, 2);
    }

    public function recomputeTotal(): float
    {
        $this->total = (float) ($this->tax_2551q ?? 0)
            + (float) ($this->tax_1701q ?? 0)
            + (float) ($this->cash_in ?? 0)
            + (float) ($this->fee_2551q ?? 0)
            + (float) ($this->fee_1701q ?? 0)
            + (float) ($this->fee_bookkeeping ?? 0);

        return $this->total;
    }
}
