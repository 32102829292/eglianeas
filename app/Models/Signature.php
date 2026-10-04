<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Signature extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role',
        'policy_version',
        'signed_at',
        'signature_path',
    ];

    protected function casts(): array
    {
        return [
            'signed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether a signature path is recorded, without contacting remote storage.
     * See Announcement::hasImagePath() for why list rendering must not call the
     * network-backed hasImage().
     */
    public function hasImagePath(): bool
    {
        return $this->signature_path !== null && $this->signature_path !== '';
    }

    /**
     * Authoritative check that the file is present in storage. Memoised because
     * it is a network round trip.
     */
    public function hasImage(): bool
    {
        if ($this->hasImageMemo !== null) {
            return $this->hasImageMemo;
        }

        return $this->hasImageMemo = $this->hasImagePath()
            && Storage::disk('supabase')->exists($this->signature_path);
    }

    private ?bool $hasImageMemo = null;
}