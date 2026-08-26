<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailySnapshot extends Model
{
    protected $fillable = [
        'date',
        'revenue_collected',
        'new_billings',
        'overdue_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'revenue_collected' => 'float',
            'new_billings' => 'integer',
            'overdue_count' => 'integer',
        ];
    }
}
