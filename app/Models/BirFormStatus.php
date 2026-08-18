<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirFormStatus extends Model
{
    use HasFactory;

    public const FORM_TYPES = [
        'EFPS',
        '2551Q',
        '1701',
        '1701Q',
        '2550Q',
        '1601C',
        '1601EQ',
        '0619E',
        '2550M',
        '0619F',
        '1601FQ',
        '1702Q',
    ];

    public const STATUS_FILED = 'filed';
    public const STATUS_NOT_FILED = 'not_filed';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUSES = [
        self::STATUS_FILED => 'Filed',
        self::STATUS_NOT_FILED => 'Not Filed',
        self::STATUS_NOT_APPLICABLE => 'N/A',
    ];

    protected $fillable = [
        'client_id',
        'form_type',
        'status',
        'applicable',
        'updated_by',
    ];

    protected $casts = [
        'applicable' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }
}
