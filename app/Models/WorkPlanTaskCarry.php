<?php

namespace App\Models;

use App\Casts\DateOnlyCast;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkPlanTaskCarry extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_plan_task_id',
        'user_id',
        'from_date',
        'to_date',
        'reason',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'from_date' => DateOnlyCast::class,
            'to_date' => DateOnlyCast::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(WorkPlanTask::class, 'work_plan_task_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
