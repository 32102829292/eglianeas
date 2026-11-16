<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkPlanLegend extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'category',
        'is_active',
        'is_non_working',
        'requires_details',
        'is_custom',
        'sort_order',
        'usage_instructions',
        'created_by',
        'edited_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_non_working' => 'boolean',
            'requires_details' => 'boolean',
            'is_custom' => 'boolean',
            'sort_order' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public const CATEGORY_ACTIVITY = 'Activity / Task Codes';

    public const CATEGORY_PROJECT = 'Special / Project Codes';

    public const CODE_RANGE_START = 'A';

    public const CODE_RANGE_END = 'T';

    public const PROJECT_CODE_PREFIX = 'T';

    public const PROJECT_CODE_START = 1;

    public const PROJECT_CODE_END = 8;

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(WorkPlanTask::class, 'legend_code', 'code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('is_active', false);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category)->where('is_active', true);
    }

    public static function getAllActiveCodes(): array
    {
        return self::active()->orderBy('code')->pluck('code')->all();
    }

    public static function getLegendsByCategory(): array
    {
        return self::active()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get()
            ->groupBy('category');
    }

    /**
     * Default activity codes transcribed from the official
     * "WORK PLAN_with_Daily_Accomplishment_Report" workbook.
     */
    public static function defaultLegends(): array
    {
        $activity = self::CATEGORY_ACTIVITY;
        $project = self::CATEGORY_PROJECT;

        return [
            ['code' => 'A', 'name' => 'BIR Registration / New COR', 'category' => $activity, 'sort_order' => 1],
            ['code' => 'B', 'name' => 'Secure New Service Invoice', 'category' => $activity, 'sort_order' => 2],
            ['code' => 'C', 'name' => 'Secure New Sales Invoice', 'category' => $activity, 'sort_order' => 3],
            ['code' => 'D', 'name' => 'Secure New Cash Voucher / PO', 'category' => $activity, 'sort_order' => 4],
            ['code' => 'E', 'name' => 'Secure New Books (CRJ/CDJ/Ledger/Journal)', 'category' => $activity, 'sort_order' => 5],
            ['code' => 'F', 'name' => 'BOOKKEEPING', 'category' => $activity, 'sort_order' => 6],
            ['code' => 'G', 'name' => 'BIR Closure', 'category' => $activity, 'sort_order' => 7],
            ['code' => 'H', 'name' => 'SSS / Pag-IBIG / PhilHealth Registration', 'category' => $activity, 'sort_order' => 8],
            ['code' => 'I', 'name' => 'Annual / Quarterly Income Tax Return', 'category' => $activity, 'sort_order' => 9],
            ['code' => 'J', 'name' => 'Percentage Tax (2551Q) / VAT (2550Q)', 'category' => $activity, 'sort_order' => 10],
            ['code' => 'K', 'name' => 'Withholding Tax (0619E / 1601C / 1601EQ)', 'category' => $activity, 'sort_order' => 11],
            ['code' => 'L', 'name' => 'Audited / Unaudited FS', 'category' => $activity, 'sort_order' => 12],
            ['code' => 'M', 'name' => "Mayor's Permit Renewal / DTI / PhilGEPS", 'category' => $activity, 'sort_order' => 13],
            ['code' => 'N', 'name' => 'Tax Clearance / ATP / TIN ID Processing', 'category' => $activity, 'sort_order' => 14],
            ['code' => 'O', 'name' => 'Weekly Bookkeeping Summary / Accomplishment', 'category' => $activity, 'sort_order' => 15],
            ['code' => 'P', 'name' => 'Photocopy Faded Receipts / Log Book Update', 'category' => $activity, 'sort_order' => 16],
            ['code' => 'Q', 'name' => 'PICKUP OF DOCUMENTS ,BOOKS', 'category' => $activity, 'sort_order' => 17],
            ['code' => 'R', 'name' => 'RETURN OF DOCUMENTS, BOOKS', 'category' => $activity, 'sort_order' => 18],
            ['code' => 'S', 'name' => 'Rest Day / Leave / Holiday', 'category' => $activity, 'sort_order' => 19, 'is_non_working' => true, 'usage_instructions' => "Mark rest days / leave / holidays with 'S'."],
            ['code' => 'T', 'name' => 'Other (specify in remarks)', 'category' => $activity, 'sort_order' => 20, 'requires_details' => true, 'usage_instructions' => 'Use for other tasks and write the details in the remarks.'],
            ['code' => 'T1', 'name' => 'Priority / Rush Client', 'category' => $project, 'sort_order' => 21],
            ['code' => 'T2', 'name' => 'Follow-up / Reprocessing', 'category' => $project, 'sort_order' => 22],
            ['code' => 'T3', 'name' => 'Document Retrieval / Lost Docs', 'category' => $project, 'sort_order' => 23],
            ['code' => 'T4', 'name' => 'Client Meeting / Coordination', 'category' => $project, 'sort_order' => 24],
            ['code' => 'T5', 'name' => 'Training / Internal Meeting', 'category' => $project, 'sort_order' => 25],
            ['code' => 'T6', 'name' => 'System / eBIR / eFPS Encoding', 'category' => $project, 'sort_order' => 26],
            ['code' => 'T7', 'name' => 'Field Work / BIR / LGU Visit', 'category' => $project, 'sort_order' => 27],
            ['code' => 'T8', 'name' => 'Others (write details)', 'category' => $project, 'sort_order' => 28, 'requires_details' => true],
        ];
    }

    public static function initializeDefaultLegends(?int $createdBy = null): int
    {
        $createdBy ??= User::query()
            ->whereIn('role', [User::ROLE_ADMIN, User::ROLE_SUPERVISOR])
            ->orderBy('id')
            ->value('id');

        $count = 0;

        foreach (self::defaultLegends() as $legend) {
            $model = self::query()->where('code', $legend['code'])->first();

            // Official seeding must never overwrite a custom legend that happens
            // to share a code with an official one.
            if ($model && $model->is_custom) {
                continue;
            }

            if (! $model) {
                if (! $createdBy) {
                    continue;
                }

                $model = new self(['code' => $legend['code']]);
                $model->created_by = $createdBy;
            }

            $model->fill([
                'name' => $legend['name'],
                'category' => $legend['category'],
                'sort_order' => $legend['sort_order'],
                'is_active' => true,
                'is_non_working' => $legend['is_non_working'] ?? false,
                'requires_details' => $legend['requires_details'] ?? false,
                'is_custom' => false,
                'usage_instructions' => $legend['usage_instructions'] ?? null,
            ]);

            $model->save();
            $count++;
        }

        return $count;
    }

    public function scopeOfficial(Builder $query): Builder
    {
        return $query->where('is_custom', false);
    }

    public function scopeCustom(Builder $query): Builder
    {
        return $query->where('is_custom', true);
    }

    public function isNonWorking(): bool
    {
        return (bool) $this->is_non_working;
    }

    public function requiresDetails(): bool
    {
        return (bool) $this->requires_details;
    }
}
