<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class KaizenEvidence extends Model
{
    use HasFactory;

    protected $table = 'kaizen_evidences';

    protected $fillable = [
        "kaizen_concern_id",
        "progress_update_id",
        "uploaded_by",
        "original_name",
        "path",
        "mime_type",
        "size",
    ];

    public function concern(): BelongsTo
    {
        return $this->belongsTo(KaizenConcern::class, "kaizen_concern_id");
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, "uploaded_by");
    }

    public function progressUpdate(): BelongsTo
    {
        return $this->belongsTo(KaizenConcernProgressUpdate::class, "progress_update_id");
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk("local")->exists($this->path);
    }
}
