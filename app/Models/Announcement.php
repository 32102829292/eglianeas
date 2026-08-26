<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'image_path',
        'posted_at',
        'posted_by',
    ];

    protected function casts(): array
    {
        return [
            'posted_at' => 'datetime',
        ];
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function hasImage(): bool
    {
        return $this->image_path !== null
            && Storage::disk('supabase')->exists($this->image_path);
    }

    public function imageUrl(): ?string
    {
        if (! $this->hasImage()) {
            return null;
        }

        return route('announcements.image', $this);
    }

    public function deleteImage(): void
    {
        if ($this->image_path && Storage::disk('supabase')->exists($this->image_path)) {
            Storage::disk('supabase')->delete($this->image_path);
        }
    }
}
