<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'cash_in',
        'total',
        'status',
        'paid_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quarter' => 'integer',
            'year' => 'integer',
            'due_date' => 'date',
            'cash_in' => 'float',
            'total' => 'float',
            'paid_at' => 'datetime',
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

    public function lineItems(): HasMany
    {
        return $this->hasMany(BillingLineItem::class);
    }

    public function remittances()
    {
        return $this->lineItems()->remittances();
    }

    public function professionalFees()
    {
        return $this->lineItems()->professionalFees();
    }

    public function bookkeepingFees()
    {
        return $this->lineItems()->bookkeepingFees();
    }

    public function cashInItem()
    {
        return $this->lineItems()->cashIn();
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }

    public static function defaultDueDate(?int $quarter, ?int $year): Carbon
    {
        return Carbon::create($year ?: self::currentYear(), max(1, (int) $quarter) * 3, 1)->endOfMonth();
    }

    public function syncStatus(): void
    {
        if ($this->isPaid()) {
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
        $lineTotal = (float) $this->lineItems()->sum('amount');
        $this->total = $lineTotal + (float) ($this->cash_in ?? 0);

        return $this->total;
    }
}
