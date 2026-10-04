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

    /**
     * Whether an image path is recorded, without contacting remote storage.
     *
     * List and page rendering must use this rather than hasImage(): hasImage()
     * performs a network round trip to Supabase Storage, so calling it per row
     * (and the card template called it three times per row) put many remote
     * requests in front of every render of the admin and public pages. The
     * <img> tags already degrade to a placeholder via onerror, and the serving
     * route still verifies the file before redirecting.
     */
    public function hasImagePath(): bool
    {
        return $this->image_path !== null && $this->image_path !== '';
    }

    /**
     * Authoritative check that the image is actually present in storage.
     *
     * Memoised because it is a network call: repeated calls for the same model
     * within one request reuse the first result.
     */
    public function hasImage(): bool
    {
        if ($this->hasImageMemo !== null) {
            return $this->hasImageMemo;
        }

        return $this->hasImageMemo = $this->hasImagePath()
            && Storage::disk('supabase')->exists($this->image_path);
    }

    private ?bool $hasImageMemo = null;

    public function imageUrl(): ?string
    {
        /* Uses the cheap path check: this is called while rendering lists, and
           the announcements.image route re-verifies the file before serving. */
        if (! $this->hasImagePath()) {
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
