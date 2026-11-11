<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffEvaluationPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function evaluations(): HasMany
    {
        return $this->hasMany(StaffTaskEvaluation::class, 'evaluation_period_id');
    }

    public function contributionRecords(): HasMany
    {
        return $this->hasMany(StaffContributionRecord::class, 'evaluation_period_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeContainingDate(Builder $query, \Carbon\Carbon $date): Builder
    {
        return $query->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString());
    }

    public function dateRangeLabel(): string
    {
        return $this->start_date->format('M j, Y').' – '.$this->end_date->format('M j, Y');
    }
}
