<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TrackerAssignment extends Model
{
    use HasFactory;

    protected $table = 'tracker_assignments';

    protected $fillable = [
        'instance_id',
        'staff_id',
        'staff_name',
        'completed',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'completed' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function instance(): BelongsTo
    {
        return $this->belongsTo(TrackerInstance::class, 'instance_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    public function toggleComplete(): void
    {
        $this->completed = ! $this->completed;
        $this->completed_at = $this->completed ? now() : null;
        $this->save();

        $this->instance->syncOverallStatus();
    }
}
