<?php

namespace App\Models;

use App\Support\BillingFrequency;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingLineItem extends Model
{
    use HasFactory;

    public const CATEGORY_BIR_REMITTANCE = 'bir_remittance';
    public const CATEGORY_PROFESSIONAL_FEE = 'professional_fee';
    public const CATEGORY_BOOKKEEPING_FEE = 'bookkeeping_fee';
    public const CATEGORY_POST_CLOSING_TB = 'post_closing_tb';
    public const CATEGORY_INVENTORY_LIST = 'inventory_list';
    public const CATEGORY_OTHER_ATTACHMENT = 'other_attachment';
    public const CATEGORY_DATA_ENTRY = 'data_entry';

    /**
     * Ad-hoc charge created from the billing page itself (e.g. "Annual BIR
     * Registration"). It has no workbook column of its own — BillingSummaryMatrix
     * collects it into the trailing Custom Fee column — but it is a first-class
     * category so it can carry a frequency and notes like any other line.
     */
    public const CATEGORY_CUSTOM = 'custom';

    public const CATEGORIES = [
        self::CATEGORY_BIR_REMITTANCE => 'BIR Remittance',
        self::CATEGORY_PROFESSIONAL_FEE => 'Professional Fee',
        self::CATEGORY_BOOKKEEPING_FEE => 'Bookkeeping Fee',
        self::CATEGORY_POST_CLOSING_TB => 'Post-Closing Trial Balance',
        self::CATEGORY_INVENTORY_LIST => 'Inventory List (Notarized)',
        self::CATEGORY_OTHER_ATTACHMENT => 'Other Attachment',
        self::CATEGORY_DATA_ENTRY => 'Data Entry',
        self::CATEGORY_CUSTOM => 'Custom Item',
    ];

    protected $fillable = [
        'billing_id',
        'category',
        'form_type',
        'label',
        'month',
        'amount',
        'fee_rate_id',
        'frequency',
        'manual_include',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'month' => 'integer',
            'amount' => 'float',
            'manual_include' => 'boolean',
        ];
    }

    /**
     * Frequency this row was billed under.
     *
     * Snapshotted at save time (rather than resolved from the current
     * BillingFrequency map when read) so an old statement keeps reporting the
     * frequency it was created with if the master map is ever revised.
     */
    public function frequencyLabel(): string
    {
        return BillingFrequency::label($this->frequency);
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

    public function scopePostClosingTb($query)
    {
        return $query->where('category', self::CATEGORY_POST_CLOSING_TB);
    }

    public function scopeInventoryList($query)
    {
        return $query->where('category', self::CATEGORY_INVENTORY_LIST);
    }

    public function scopeOtherAttachment($query)
    {
        return $query->where('category', self::CATEGORY_OTHER_ATTACHMENT);
    }

    public function scopeDataEntry($query)
    {
        return $query->where('category', self::CATEGORY_DATA_ENTRY);
    }

    public function scopeCashIn($query)
    {
        return $query->where('category', self::CATEGORY_BIR_REMITTANCE)->whereNull('form_type');
    }
}
