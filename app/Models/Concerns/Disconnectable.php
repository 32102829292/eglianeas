<?php

namespace App\Models\Concerns;

use App\Models\ClientDisconnection;
use Illuminate\Database\Eloquent\Builder;

trait Disconnectable
{
    public function disconnections()
    {
        return $this->hasMany(ClientDisconnection::class, 'client_id')->latest('id');
    }

    public function activeDisconnection(): ?ClientDisconnection
    {
        return $this->disconnections()->whereNull('reconnected_at')->first();
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->whereDoesntHave('disconnections', fn ($q) => $q->whereNull('reconnected_at'));
    }
}