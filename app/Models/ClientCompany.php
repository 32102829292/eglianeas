<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClientCompany extends Model
{
    use HasFactory;

    protected $fillable = ['client_id', 'branch_number', 'company_code', 'company_name', 'needs_company_completion', 'business_type', 'line_of_business', 'bir_registration_type', 'business_address', 'latitude', 'longitude', 'business_email', 'business_contact_no'];
    protected $casts = ['branch_number' => 'integer', 'needs_company_completion' => 'boolean', 'latitude' => 'decimal:7', 'longitude' => 'decimal:7'];

    public function client(): BelongsTo { return $this->belongsTo(User::class, 'client_id')->withTrashed(); }
    public function billings(): HasMany { return $this->hasMany(Billing::class, 'client_company_id'); }
    public function birFormStatuses(): HasMany { return $this->hasMany(BirFormStatus::class, 'client_company_id'); }
    public function birForms(): HasMany { return $this->hasMany(BirForm::class, 'client_company_id'); }
}
