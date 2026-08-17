<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AboutContent extends Model
{
    protected $fillable = ['mission', 'vision'];

    public static function instance(): static
    {
        return static::firstOrNew(['id' => 1]);
    }
}
