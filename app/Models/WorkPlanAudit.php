<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkPlanAudit extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'work_plan_task_id',
        'user_id',
        'action',
        'previous_values',
        'new_values',
        'change_reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'new_values' => 'array',
            'changed_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkPlanTask::class, 'work_plan_task_id');
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
