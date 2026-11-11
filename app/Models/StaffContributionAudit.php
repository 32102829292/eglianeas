<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffContributionAudit extends Model
{
    use HasFactory;

    protected $fillable = [
        'contribution_id',
        'action',
        'previous_points',
        'new_points',
        'adjustment_reason',
        'performed_by',
    ];

    public function contribution(): BelongsTo
    {
        return $this->belongsTo(StaffContributionRecord::class, 'contribution_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
}
