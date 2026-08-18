<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingLineItem extends Model
{
    use HasFactory;

    public const CATEGORY_BIR_REMITTANCE = 'bir_remittance';
    public const CATEGORY_PROFESSIONAL_FEE = 'professional_fee';
    public const CATEGORY_BOOKKEEPING_FEE = 'bookkeeping_fee';

    public const CATEGORIES = [
        self::CATEGORY_BIR_REMITTANCE => 'BIR Remittance',
        self::CATEGORY_PROFESSIONAL_FEE => 'Professional Fee',
        self::CATEGORY_BOOKKEEPING_FEE => 'Bookkeeping Fee',
    ];

    protected $fillable = [
        'billing_id',
        'category',
        'form_type',
        'label',
        'month',
        'amount',
        'fee_rate_id',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'amount' => 'float',
        ];
    }

    public function billing(): BelongsTo
    {
        return $this->belongsTo(Billing::class);
    }

    public function feeRate(): BelongsTo
    {
        return $this->belongsTo(FeeRate::class);
    }

    public function money(): string
    {
        return '₱'.number_format($this->amount, 2);
    }

    public function scopeRemittances($query)
    {
        return $query->where('category', self::CATEGORY_BIR_REMITTANCE);
    }

    public function scopeProfessionalFees($query)
    {
        return $query->where('category', self::CATEGORY_PROFESSIONAL_FEE);
    }

    public function scopeBookkeepingFees($query)
    {
        return $query->where('category', self::CATEGORY_BOOKKEEPING_FEE);
    }

    public function scopeCashIn($query)
    {
        return $query->where('category', self::CATEGORY_BIR_REMITTANCE)->whereNull('form_type');
    }
}
