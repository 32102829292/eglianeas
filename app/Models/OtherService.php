<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OtherService extends Model
{
    use HasFactory;

    public const STATUS_UNPAID = 'unpaid';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    public const STATUSES = [
        self::STATUS_UNPAID => 'Unpaid',
        self::STATUS_PAID => 'Paid',
        self::STATUS_OVERDUE => 'Overdue',
    ];

    protected $fillable = [
        'client_id',
        'service_type_id',
        'custom_label',
        'amount',
        'requested_at',
        'notes',
        'status',
        'due_date',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'requested_at' => 'datetime',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }

    public function trackerInstance(): HasOne
    {
        return $this->hasOne(TrackerInstance::class, 'other_service_id');
    }

    public function serviceName(): string
    {
        return $this->custom_label ?: ($this->serviceType?->label ?? 'Other Service');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }

    public function syncStatus(): void
    {
        if ($this->isPaid()) {
            return;
        }

        $this->status = $this->isOverdue() ? self::STATUS_OVERDUE : self::STATUS_UNPAID;
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function money(?float $value = null): string
    {
        return '₱'.number_format($value ?? $this->amount, 2);
    }
}
