<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One settled Billing / Payment entry. Points at exactly one bookkeeping
 * target through `billable`, so confirming a payment marks that target and
 * never its siblings — the behaviour the previous client+period heuristic
 * could not guarantee.
 */
class CashPayment extends Model
{
    use HasFactory;

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_PAID = 'paid';

    public const STATUSES = [
        self::STATUS_PARTIAL => 'Partial',
        self::STATUS_PAID => 'Paid',
    ];

    protected $fillable = [
        'billable_type',
        'billable_id',
        'client_id',
        'billing_id',
        'amount',
        'paid_on',
        'method',
        'status',
        'idempotency_key',
        'notes',
        'recorded_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
        ];
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class, 'billing_id');
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function scopeForClient(Builder $query, int $clientId): Builder
    {
        return $query->where('client_id', $clientId);
    }

    public function scopePaidOn(Builder $query, $date): Builder
    {
        return $query->whereDate('paid_on', $date);
    }

    public function scopeBetween(Builder $query, $start, $end): Builder
    {
        return $query->whereDate('paid_on', '>=', $start)
            ->whereDate('paid_on', '<=', $end);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
