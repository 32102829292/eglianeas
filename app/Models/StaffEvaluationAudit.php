<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffEvaluationAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'evaluation_id',
        'action',
        'previous_values',
        'new_values',
        'adjustment_reason',
        'performed_by',
    ];

    protected function casts(): array
    {
        return [
            'previous_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(StaffTaskEvaluation::class, 'evaluation_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
