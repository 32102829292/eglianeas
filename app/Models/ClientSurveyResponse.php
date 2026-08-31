<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientSurveyResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'overall_rating',
        'service_rating',
        'portal_rating',
        'comments',
        'submitted_at',
    ];

    protected function casts(): array
    {
        return [
            'overall_rating' => 'integer',
            'service_rating' => 'integer',
            'portal_rating' => 'integer',
            'submitted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return match ($this->overall_rating) {
            5 => 'Very satisfied',
            4 => 'Satisfied',
            3 => 'Neutral',
            2 => 'Dissatisfied',
            default => 'Very dissatisfied',
        };
    }
}
