<?php

namespace App\Models;

use App\Support\Quarter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class Billing extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_UNPAID = 'unpaid';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_DRAFT = 'draft';

    public const STATUSES = [
        self::STATUS_DRAFT => 'Draft',
        self::STATUS_PENDING => 'Pending',
        self::STATUS_UNPAID => 'Unpaid',
        self::STATUS_OVERDUE => 'Overdue',
        self::STATUS_PAID => 'Paid',
    ];

    // Statuses that are considered "active" billings (visible to clients and
    // counted in financial summaries). Everything else (draft) is a prepared
    // statement waiting for admin finalization.
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_UNPAID,
        self::STATUS_OVERDUE,
        self::STATUS_PAID,
    ];

    public const QUARTERS = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th'];

    /**
     * The complete set of filterable calendar quarters for the admin billing
     * and collections toolbars: Q1–Q4 for every year that has billing records,
     * plus the current year, ordered newest first. Every quarter of a year is
     * always offered so a sparse period (e.g. only Q4 finalized so far) can
     * still be selected and will simply return empty results.
     */
    public static function filterQuarters(): Collection
    {
        $years = self::query()
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(fn ($year): int => (int) $year)
            ->push((int) now()->format('Y'))
            ->unique()
            ->sortByDesc(fn (int $year): int => $year)
            ->values();

        $quarters = new Collection;
        foreach ($years as $year) {
            for ($q = 4; $q >= 1; $q--) {
                $quarters->push(new Quarter($year, $q));
            }
        }

        return $quarters;
    }

    protected $fillable = [
        'client_id',
        'period_label',
        'quarter',
        'year',
        'due_date',
        'cash_in',
        'total',
        'status',
        'paid_at',
        'reminder_sent_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'quarter' => 'integer',
            'year' => 'integer',
            'due_date' => 'date',
            'cash_in' => 'float',
            'total' => 'float',
            'paid_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(BillingLineItem::class);
    }

    public function remittances()
    {
        return $this->lineItems()->remittances();
    }

    public function professionalFees()
    {
        return $this->lineItems()->professionalFees();
    }

    public function bookkeepingFees()
    {
        return $this->lineItems()->bookkeepingFees();
    }

    public function postClosingTb()
    {
        return $this->lineItems()->postClosingTb();
    }

    public function inventoryListItems()
    {
        return $this->lineItems()->inventoryList();
    }

    public function otherAttachmentItems()
    {
        return $this->lineItems()->otherAttachment();
    }

    public function dataEntryItems()
    {
        return $this->lineItems()->dataEntry();
    }

    public function cashInItem()
    {
        return $this->lineItems()->cashIn();
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isActive(): bool
    {
        return in_array($this->status, self::ACTIVE_STATUSES, true);
    }

    public function scopeActiveOnly(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Filters to billings issued within a specific calendar period. Uses the
     * billing's own issued period (quarter + year) rather than any date.
     */
    public function scopeForPeriod(Builder $query, Quarter $period): Builder
    {
        return $query->where('year', $period->year)
            ->where('quarter', $period->quarter);
    }

    /**
     * Filters to billings that were actually PAID within a calendar period.
     * The payment date (paid_at) drives the quarter here, not the bill period.
     */
    public function scopePaidDuring(Builder $query, Quarter $period): Builder
    {
        return $query->where('status', self::STATUS_PAID)
            ->where('paid_at', '>=', $period->start())
            ->where('paid_at', '<', $period->nextQuarterStart());
    }

    /**
     * Distinct billing periods (year + quarter) across a client's active
     * statements, newest period first.
     */
    public static function billedPeriodsFor(int $clientId): array
    {
        return self::query()
            ->where('client_id', $clientId)
            ->activeOnly()
            ->whereNotNull('year')
            ->whereNotNull('quarter')
            ->select('year', 'quarter')
            ->distinct()
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->get()
            ->map(fn ($row) => new Quarter((int) $row->year, (int) $row->quarter))
            ->all();
    }

    /**
     * Distinct payment periods derived from the paid_at date of a client's paid
     * billings, newest period first.
     */
    public static function paymentPeriodsFor(int $clientId): array
    {
        $paidAts = self::query()
            ->where('client_id', $clientId)
            ->where('status', self::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->get(['paid_at']);

        $quarters = [];
        foreach ($paidAts as $billing) {
            $quarter = Quarter::fromDate($billing->paid_at);
            $quarters[$quarter->key()] = $quarter;
        }
        krsort($quarters);

        return array_values($quarters);
    }

    /**
     * The quarter the client's view should default to for an available set of
     * periods: the current calendar quarter when it contains records, otherwise
     * the most recent period with records. An explicit, strictly validated key
     * wins — including the current quarter even when it has no records yet
     * (it is always offered in the dropdown). Any other period the client has
     * no records for falls back to the same default.
     */
    public static function resolveViewableQuarter(array $available, ?string $requested): Quarter
    {
        $candidate = Quarter::fromKey($requested);
        $current = Quarter::current();

        if ($candidate !== null) {
            foreach ($available as $period) {
                if ($period->equals($candidate)) {
                    return $period;
                }
            }
            if ($candidate->equals($current)) {
                return $current;
            }
        }

        foreach ($available as $period) {
            if ($period->equals($current)) {
                return $current;
            }
        }

        return $available[0] ?? $current;
    }

    /**
     * Quarter options for the client's period dropdown: the current calendar
     * quarter first (even when it has no records yet), then every period with
     * records, newest first.
     */
    public static function dropdownPeriods(array $available): array
    {
        $quarters = [Quarter::current()];
        foreach ($available as $period) {
            if (! $period->equals($quarters[0])) {
                $quarters[] = $period;
            }
        }

        return $quarters;
    }

    public function isOverdue(): bool
    {
        return ! $this->isPaid() && $this->due_date !== null && $this->due_date->lt(now()->startOfDay());
    }

    public static function defaultDueDate(?int $quarter, ?int $year): Carbon
    {
        return Carbon::create($year ?: self::currentYear(), max(1, (int) $quarter) * 3, 1)->endOfMonth();
    }

    public function syncStatus(): void
    {
        if ($this->isPaid() || $this->isDraft()) {
            return;
        }

        $this->status = $this->isOverdue() ? self::STATUS_OVERDUE : self::STATUS_UNPAID;
    }

    public static function currentYear(): int
    {
        return (int) now()->format('Y');
    }

    public static function currentQuarter(): int
    {
        return (int) ceil(((int) now()->format('n')) / 3);
    }

    /**
     * The quarter that should be billed next for a given client and year.
     * Falls back to Q1 when no billings exist for the period. Drafts still
     * occupy their quarter slot, so they are included when checking what is
     * already present.
     */
    public static function nextQuarterFor(int $clientId, int $year): int
    {
        $existing = self::where('client_id', $clientId)
            ->where('year', $year)
            ->whereNotNull('quarter')
            ->pluck('quarter')
            ->map(fn ($q) => (int) $q)
            ->sort()
            ->values();

        for ($q = 1; $q <= 4; $q++) {
            if (! $existing->contains($q)) {
                return $q;
            }
        }

        // All four quarters already exist for this client/year.
        return 0;
    }

    /**
     * Builds the next-quarter (same client, same year) billing from the given
     * paid billing as a template. Returns null when no next quarter exists
     * (Q4), or when a billing already exists for the next quarter.
     */
    public static function makeNextDraft(Billing $paid): ?self
    {
        if (! $paid->quarter || ! $paid->year || $paid->quarter >= 4) {
            return null;
        }

        $nextQuarter = $paid->quarter + 1;

        $exists = self::where('client_id', $paid->client_id)
            ->where('quarter', $nextQuarter)
            ->where('year', $paid->year)
            ->exists();

        if ($exists) {
            return null;
        }

        return DB::transaction(function () use ($paid, $nextQuarter) {
            $draft = new self;
            $draft->client_id = $paid->client_id;
            $draft->quarter = $nextQuarter;
            $draft->year = $paid->year;
            $draft->period_label = strtoupper(self::QUARTERS[$nextQuarter]).' QUARTER '.$paid->year.' BILLING';
            $draft->cash_in = $paid->cash_in;
            $draft->due_date = self::defaultDueDate($nextQuarter, $paid->year);
            $draft->status = self::STATUS_DRAFT;
            $draft->created_by = $paid->created_by;
            $draft->updated_by = $paid->updated_by;
            $draft->save();

            foreach ($paid->lineItems as $item) {
                BillingLineItem::create([
                    'billing_id' => $draft->id,
                    'category' => $item->category,
                    'form_type' => $item->form_type,
                    'label' => $item->label,
                    'month' => $item->month,
                    'amount' => $item->amount,
                    'fee_rate_id' => $item->fee_rate_id,
                ]);
            }

            $draft->recomputeTotal();
            $draft->save();

            return $draft;
        });
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    public function periodTitle(): string
    {
        if ($this->year && $this->quarter) {
            return self::QUARTERS[$this->quarter].' Quarter '.$this->year;
        }

        return $this->period_label ?: 'Billing statement';
    }

    public function periodTitleUppercase(): string
    {
        return strtoupper($this->periodTitle());
    }

    public function money(?float $value): string
    {
        return '₱'.number_format($value ?? 0, 2);
    }

    public function recomputeTotal(): float
    {
        $lineTotal = (float) $this->lineItems()->sum('amount');
        $this->total = $lineTotal + (float) ($this->cash_in ?? 0);

        return $this->total;
    }
}
