<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientConcern extends Model
{
    use HasFactory;

    public const STATUS_FREQUENT = 'frequent';
    public const STATUS_SELDOM = 'seldom';
    public const STATUS_RARE = 'rare';

    public const STATUSES = [
        self::STATUS_FREQUENT => 'Frequent',
        self::STATUS_SELDOM => 'Seldom',
        self::STATUS_RARE => 'Rare',
    ];

    public const SUBMITTED_BY_CLIENT = 'client';
    public const SUBMITTED_BY_STAFF = 'staff';

    protected $fillable = [
        'client_id',
        'related_service_id',
        'date_identified',
        'description_of_issue',
        'proposed_solution',
        'status',
        'submitted_by',
        'reviewed',
    ];

    protected function casts(): array
    {
        return [
            'date_identified' => 'date',
            'reviewed' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function relatedService(): BelongsTo
    {
        return $this->belongsTo(TrackerService::class, 'related_service_id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function isClientSubmitted(): bool
    {
        return $this->submitted_by === self::SUBMITTED_BY_CLIENT;
    }

    public function isNew(): bool
    {
        return $this->isClientSubmitted() && ! $this->reviewed;
    }
}
