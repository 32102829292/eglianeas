<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyJournalAssessmentRevision extends Model
{
    protected $fillable = [
        'daily_journal_id',
        'reviewer_id',
        'action',
        'previous_score',
        'previous_category',
        'previous_level',
        'previous_explanation',
        'previous_tags',
        'previous_source',
        'previous_status',
        'new_score',
        'new_category',
        'new_level',
        'new_explanation',
        'new_tags',
        'new_source',
        'new_status',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'previous_tags' => 'array',
            'new_tags' => 'array',
            'previous_score' => 'integer',
            'new_score' => 'integer',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(DailyJournal::class, 'daily_journal_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
