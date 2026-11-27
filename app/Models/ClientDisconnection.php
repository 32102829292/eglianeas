<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientDisconnection extends Model
{
    public const REASONS = [
        'not_active' => 'Not active',
        'agreed_closure' => 'Agreed to close',
        'other' => 'Others',
    ];

    protected $fillable = [
        'client_id', 'reason', 'details', 'disconnected_at',
        'disconnected_by', 'reconnected_at', 'reconnected_by',
    ];

    protected $casts = [
        'disconnected_at' => 'date',
        'reconnected_at' => 'date',
    ];

    public function client()
    {
        $relation = $this->belongsTo(config('disconnection.client_model'), 'client_id');

        // Keep showing the name even if the client model uses soft deletes.
        if (method_exists($relation->getRelated(), 'bootSoftDeletes')) {
            $relation->withTrashed();
        }

        return $relation;
    }

    public function reasonLabel(): string
    {
        return self::REASONS[$this->reason] ?? $this->reason;
    }
}