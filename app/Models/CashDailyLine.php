<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single salary or expense deduction on a day's Cash Daily Position.
 */
class CashDailyLine extends Model
{
    use HasFactory;

    public const TYPE_SALARY = 'salary';

    public const TYPE_EXPENSE = 'expense';

    /**
     * A cash RECEIPT line (the worksheet's ADD block): a client payment
     * mirror or a free-text receipt. Kept on the same table as salaries and
     * expenses so one day's movements live together.
     */
    public const TYPE_ADDITION = 'addition';

    public const TYPES = [
        self::TYPE_SALARY => 'Salary',
        self::TYPE_EXPENSE => 'Expense',
        self::TYPE_ADDITION => 'Cash received',
    ];

    /** The types that subtract from the day's cash (the LESS block). */
    public const LESS_TYPES = [
        self::TYPE_SALARY,
        self::TYPE_EXPENSE,
    ];

    protected $fillable = [
        'cash_daily_position_id',
        'client_id',
        'type',
        'category',
        'label',
        'amount',
        'source_cash_payment_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
        ];
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(CashDailyPosition::class, 'cash_daily_position_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function sourcePayment(): BelongsTo
    {
        return $this->belongsTo(CashPayment::class, 'source_cash_payment_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function isAddition(): bool
    {
        return $this->type === self::TYPE_ADDITION;
    }
}
