<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    protected $fillable = ['user_id', 'position', 'name', 'department', 'reports_to', 'supervises', 'rank', 'duties', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
