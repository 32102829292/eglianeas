<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class VerificationCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'email',
        'code',
        'code_plain',
        'expires_at',
        'used_at',
        'attempts',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function matches(string $plainCode): bool
    {
        return Hash::check($plainCode, $this->code);
    }

    public static function issue(User $user, string $plainCode, int $ttlMinutes = 15): self
    {
        return static::create([
            'user_id' => $user->id,
            'email' => $user->email,
            'code' => Hash::make($plainCode),
            'code_plain' => app()->environment('local') ? $plainCode : null,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);
    }
}
