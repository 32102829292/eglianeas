<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashDailyReceipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'cash_daily_position_id',
        'file_name',
        'file_type',
        'file_path',
    ];

    public function position(): BelongsTo
    {
        return $this->belongsTo(CashDailyPosition::class, 'cash_daily_position_id');
    }
}
