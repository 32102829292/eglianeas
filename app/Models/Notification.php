<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notification extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'body',
        'type',
        'group_key',
        'reminder_count',
        'link',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
            'reminder_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }

    public function markAsRead(): self
    {
        if ($this->isUnread()) {
            $this->update(['read_at' => now()]);
        }

        return $this;
    }

    /**
     * Create a reminder notification, or bump the latest one in the same
     * group so repeated reminders collapse into a single entry with a
     * "reminded N times" indicator instead of flooding the list.
     */
    public static function remind(
        int $userId,
        string $groupKey,
        string $title,
        string $body,
        string $type = 'reminder',
        ?string $link = null
    ): self {
        $existing = static::query()
            ->where('user_id', $userId)
            ->where('group_key', $groupKey)
            ->latest('id')
            ->first();

        if ($existing) {
            $existing->reminder_count = ((int) $existing->reminder_count) + 1;
            $existing->title = $title;
            $existing->body = $body;
            $existing->type = $type;
            $existing->link = $link;
            $existing->read_at = null;
            $existing->created_at = now();
            $existing->save();

            return $existing;
        }

        return static::create([
            'user_id' => $userId,
            'group_key' => $groupKey,
            'title' => $title,
            'body' => $body,
            'type' => $type,
            'link' => $link,
            'reminder_count' => 1,
            'created_at' => now(),
        ]);
    }

    /**
     * Mark every unread notification in a group as read once the underlying
     * requirement is satisfied (e.g. sales submitted, bill paid).
     */
    public static function resolveGroup(int $userId, string $groupKey): void
    {
        static::query()
            ->where('user_id', $userId)
            ->where('group_key', $groupKey)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }
}
