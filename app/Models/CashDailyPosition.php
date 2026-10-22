<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The hand-entered figures for one day of the Cash Daily Position report:
 * the beginning balance carried in, the day's budget, and its expense lines.
 * Money received is NOT stored here — it is read from payments/billings.
 */
class CashDailyPosition extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'position_date',
        'beginning_balance',
        'budget',
        'actual_cash',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'position_date' => 'date',
            'beginning_balance' => 'decimal:2',
            'budget' => 'decimal:2',
            'actual_cash' => 'decimal:2',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CashDailyLine::class);
    }

    /** The ADD block: cash received (client-linked or free-text). */
    public function additions(): HasMany
    {
        return $this->lines()->where('type', CashDailyLine::TYPE_ADDITION);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function salaries(): HasMany
    {
        return $this->lines()->where('type', CashDailyLine::TYPE_SALARY);
    }

    public function expenses(): HasMany
    {
        return $this->lines()->where('type', CashDailyLine::TYPE_EXPENSE);
    }

    public function totalSalaries(): float
    {
        return (float) $this->lines()->where('type', CashDailyLine::TYPE_SALARY)->sum('amount');
    }

    public function totalExpenses(): float
    {
        return (float) $this->lines()->where('type', CashDailyLine::TYPE_EXPENSE)->sum('amount');
    }

    public function totalLess(): float
    {
        return $this->totalSalaries() + $this->totalExpenses();
    }

    /** The ADD block total: cash received on this day. */
    public function totalAdditions(): float
    {
        return (float) $this->lines()->where('type', CashDailyLine::TYPE_ADDITION)->sum('amount');
    }

    /** Ending balance = beginning + additions − less. */
    public function expectedEnding(): float
    {
        return (float) $this->beginning_balance + $this->totalAdditions() - $this->totalLess();
    }

    /** Overage / (shortage) = actual cash counted − expected ending. */
    public function overShort(): ?float
    {
        if ($this->actual_cash === null) {
            return null;
        }

        return (float) $this->actual_cash - $this->expectedEnding();
    }
}
