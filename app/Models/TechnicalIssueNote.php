<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TechnicalIssueNote extends Model
{
    protected $fillable = [
        'technical_issue_id',
        'user_id',
        'body',
    ];

    public function issue(): BelongsTo
    {
        return $this->belongsTo(TechnicalIssue::class, 'technical_issue_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
