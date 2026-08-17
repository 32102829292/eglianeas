<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentDelivery extends Model
{
    use HasFactory;

    public const METHOD_FACE_TO_FACE = 'face_to_face';
    public const METHOD_ONLINE = 'online';

    public const METHODS = [
        self::METHOD_FACE_TO_FACE => 'Face-to-face',
        self::METHOD_ONLINE => 'Through online (Messenger/Email)',
    ];

    protected $fillable = [
        'client_id',
        'form_type',
        'delivery_method',
        'date_received',
        'time_received',
        'remarks',
        'no_file_flag',
    ];

    protected function casts(): array
    {
        return [
            'date_received' => 'date',
            'no_file_flag' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function methodLabel(): string
    {
        return self::METHODS[$this->delivery_method] ?? ucfirst(str_replace('_', ' ', $this->delivery_method));
    }

    public function timeLabel(): ?string
    {
        if (! $this->time_received) {
            return null;
        }

        $parts = explode(':', $this->time_received);
        $h = (int) ($parts[0] ?? 0);
        $m = $parts[1] ?? '00';
        $period = $h >= 12 ? 'PM' : 'AM';
        $h12 = $h % 12 ?: 12;

        return $h12.':'.$m.' '.$period;
    }
}
