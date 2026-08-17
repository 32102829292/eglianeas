<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FeeRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'amount',
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

    public function money(): string
    {
        return '₱'.number_format($this->amount, 2);
    }
}
