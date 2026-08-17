<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CoreValue extends Model
{
    protected $fillable = ['label', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
