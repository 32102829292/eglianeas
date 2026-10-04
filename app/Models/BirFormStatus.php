<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BirFormStatus extends Model
{
    use HasFactory;

    public const STATUS_FILED = 'filed';
    public const STATUS_NOT_FILED = 'not_filed';
    public const STATUS_NOT_APPLICABLE = 'not_applicable';

    public const STATUSES = [
        self::STATUS_FILED => 'Filed',
        self::STATUS_NOT_FILED => 'Not Applicable',
        self::STATUS_NOT_APPLICABLE => 'Exempt',
    ];

    protected $fillable = [
        'client_id',
        'client_company_id',
        'form_type',
        'status',
        'applicable',
        'updated_by',
    ];

    protected $casts = [
        'applicable' => 'boolean',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(ClientCompany::class, 'client_company_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function formType(): BelongsTo
    {
        return $this->belongsTo(BirFormType::class, 'form_type', 'code');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status));
    }

    /**
     * Get the list of available form type codes from the master table.
     * Falls back to the static constant if the table is empty.
     */
    public static function getFormTypeCodes(): array
    {
        $codes = BirFormType::active()->ordered()->pluck('code')->toArray();
        return $codes ?: self::DEFAULT_FORM_TYPES;
    }

    /**
     * Get the list of available form types with names for display.
     */
    public static function getFormTypesForSelect(): array
    {
        $types = BirFormType::active()->ordered()->get(['code', 'name']);
        if ($types->isEmpty()) {
            return collect(self::DEFAULT_FORM_TYPES)->mapWithKeys(fn ($code) => [$code => $code])->toArray();
        }

        return $types->mapWithKeys(fn ($type) => [$type->code => "{$type->code} — {$type->name}"])->toArray();
    }

    public const DEFAULT_FORM_TYPES = [
        'EFPS',
        '2551Q',
        '1701',
        '1701Q',
        '2550Q',
        '1601C',
        '1601EQ',
        '0619E',
        '2550M',
        '0619F',
        '1601FQ',
        '1702Q',
        '1702',
    ];

    // Backward compatibility - static access to default form types
    public const FORM_TYPES = self::DEFAULT_FORM_TYPES;
}
