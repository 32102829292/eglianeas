<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffScoringConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'config_key',
        'config_value',
        'description',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function typedValue(): mixed
    {
        $decoded = json_decode($this->config_value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $this->config_value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $record = self::where('config_key', $key)->first();

        return $record?->typedValue() ?? $default;
    }

    public static function set(string $key, mixed $value, ?string $description = null, ?int $updatedBy = null): self
    {
        $encoded = is_bool($value) || is_int($value) || is_float($value) || is_string($value)
            ? $value
            : json_encode($value);

        return self::updateOrCreate(
            ['config_key' => $key],
            [
                'config_value' => $encoded,
                'description' => $description,
                'updated_by' => $updatedBy,
            ]
        );
    }
}
