<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ChecklistItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'checklistable_type',
        'checklistable_id',
        'title',
        'completed',
        'completed_at',
        'completed_by',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'completed_at' => 'date',
        ];
    }

    public function checklistable(): MorphTo
    {
        return $this->morphTo();
    }

    public function completer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    public function toggleComplete(?User $user = null): void
    {
        $this->completed = ! $this->completed;
        $this->completed_at = $this->completed ? now() : null;
        $this->completed_by = $this->completed ? ($user?->id) : null;
        $this->save();
    }
}