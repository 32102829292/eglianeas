<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KaizenConcernProgressUpdate extends Model
{
    use HasFactory;

    protected $table = "kaizen_concern_progress_updates";

    protected $fillable = [
        "kaizen_concern_id",
        "submitted_by",
        "actions_taken",
        "current_situation",
        "remaining_issues",
    ];

    public function concern(): BelongsTo
    {
        return $this->belongsTo(KaizenConcern::class, "kaizen_concern_id");
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, "submitted_by");
    }

    public function evidences(): HasMany
    {
        return $this->hasMany(KaizenEvidence::class, "progress_update_id")
            ->orderBy("created_at", "desc");
    }
}
