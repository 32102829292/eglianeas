<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientProfile extends Model
{
    use HasFactory;

    public const STATUS_CURRENT = 'current';
    public const STATUS_PENDING = 'pending';
    public const STATUS_DELINQUENT = 'delinquent';
    public const STATUS_CRITICAL = 'critical';

    public const STATUSES = [
        self::STATUS_CURRENT => 'Current',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_DELINQUENT => 'Delinquent',
        self::STATUS_CRITICAL => 'Critical',
    ];

    public const STATUS_NOTES = [
        self::STATUS_CURRENT => 'All filings and payments are up to date.',
        self::STATUS_PENDING => 'Some requirements are awaiting completion.',
        self::STATUS_DELINQUENT => 'Action is needed on your filings or payments.',
        self::STATUS_CRITICAL => 'Immediate attention is required.',
    ];

    public const BUSINESS_TYPES = [
        'Sole Proprietorship',
        'Partnership',
        'Corporation',
        'Cooperative',
        'One Person Corporation (OPC)',
    ];

    public const LINE_OF_BUSINESS_OPTIONS = [
        'Retail & Wholesale',
        'Food & Beverage',
        'Professional Services',
        'Manufacturing',
        'Construction',
        'Real Estate',
        'Transportation & Logistics',
        'Technology/IT Services',
        'Health & Wellness',
        'Other',
    ];

    public const BIR_REGISTRATION_TYPES = [
        'VAT',
        'Non-VAT',
        'Exempt',
    ];

    protected $fillable = [
        'user_id',
        'business_type',
        'line_of_business',
        'bir_registration_type',
        'business_address',
        'latitude',
        'longitude',
        'contact_no',
        'second_contact_name',
        'second_contact_no',
        'second_email',
        'birth_date',
        'tin_no',
        'mother_maiden_name',
        'father_name',
        'status',
        'payment_status',
        'date_started',
        'remarks',
    ];

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'date_started' => 'date',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function paymentStatusLabel(): ?string
    {
        return match ($this->payment_status) {
            'paid' => 'Paid',
            'partial' => 'Partial',
            'unpaid' => 'Unpaid',
            default => null,
        };
    }
}
