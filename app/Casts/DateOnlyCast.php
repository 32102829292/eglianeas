<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Support\Carbon;

class DateOnlyCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): Carbon
    {
        return Carbon::parse($value)->startOfDay();
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        if ($value instanceof Carbon) {
            return $value->format('Y-m-d');
        }
        
        return Carbon::parse($value)->format('Y-m-d');
    }
}