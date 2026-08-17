<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Filing extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_FILED = 'filed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pending',
        self::STATUS_FILED => 'Filed',
        self::STATUS_REJECTED => 'Rejected',
        self::STATUS_NEEDS_REVIEW => 'Needs review',
    ];

    protected $fillable = [
        'client_id',
        'type',
        'period',
        'due_date',
        'filed_at',
        'status',
        'notes',
        'file_path',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'filed_at' => 'date',
        ];
    }

    public function client()
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function isOverdue(): bool
    {
        return $this->due_date !== null && $this->due_date->isPast() && $this->status === self::STATUS_PENDING;
    }
}
