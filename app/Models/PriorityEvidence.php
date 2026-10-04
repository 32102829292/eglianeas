<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class PriorityEvidence extends Model
{
    use HasFactory;

    protected $table = 'priority_evidences';

    protected $fillable = [
        "priority_item_id",
        "uploaded_by",
        "original_name",
        "path",
        "mime_type",
        "size",
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PriorityItem::class, "priority_item_id");
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, "uploaded_by");
    }

    public function existsOnDisk(): bool
    {
        return Storage::disk("local")->exists($this->path);
    }
}
