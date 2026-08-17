<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyCertificate extends Model
{
    protected $fillable = ['label', 'file_path', 'original_name', 'mime_type', 'size', 'sort_order', 'uploaded_at'];

    protected $casts = [
        'size' => 'integer',
        'sort_order' => 'integer',
        'uploaded_at' => 'datetime',
    ];

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function isImage(): bool
    {
        return str_starts_with($this->mime_type, 'image/');
    }
}
