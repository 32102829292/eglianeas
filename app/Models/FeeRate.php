<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeRate extends Model
{
    use HasFactory;

    public const CATEGORY_PROFESSIONAL_FEE = 'professional_fee';
    public const CATEGORY_BOOKKEEPING_FEE = 'bookkeeping_fee';
    public const CATEGORY_POST_CLOSING_TB = 'post_closing_tb';
    public const CATEGORY_INVENTORY_LIST = 'inventory_list';
    public const CATEGORY_OTHER_ATTACHMENT = 'other_attachment';
    public const CATEGORY_DATA_ENTRY = 'data_entry';

    public const CATEGORIES = [
        self::CATEGORY_PROFESSIONAL_FEE => 'Professional Fee',
        self::CATEGORY_BOOKKEEPING_FEE => 'Bookkeeping Fee',
        self::CATEGORY_POST_CLOSING_TB => 'Post-Closing Trial Balance',
        self::CATEGORY_INVENTORY_LIST => 'Inventory List (Notarized)',
        self::CATEGORY_OTHER_ATTACHMENT => 'Other Attachment',
        self::CATEGORY_DATA_ENTRY => 'Data Entry',
    ];

    protected $fillable = [
        'label',
        'amount',
        'category',
        'sort_order',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'sort_order' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('amount');
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

    public function money(): string
    {
        return '₱'.number_format($this->amount, 2);
    }
}
