<?php

namespace App\Support\Bookkeeping\Concerns;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The target-side workflow shared by the Weekly, Monthly and Quarterly
 * bookkeeping targets: the standard task sequence and the partial-payment
 * balance fields.
 *
 * The sequence is expressed as a day offset from Pick-Up rather than as a chain
 * of "previous stage + 1" rules, so one table drives Pick-Up, Record, Return and
 * Payment alike and a stage can never depend on a sibling row being present.
 *
 * Balance information is deliberately additive. `payment_status`,
 * `balance_amount` and `balance_note` sit beside the existing `payment_method` /
 * `payment_at` / `actual_status` columns and never replace them, so Billing stays
 * the authoritative record of money received and the existing Paid / Unpaid
 * roll-ups keep their current meaning.
 *
 * @property string $task_type
 * @property \Illuminate\Support\Carbon|null $target_date
 * @property bool|null $target_date_auto
 * @property string|null $payment_status
 * @property float|null $balance_amount
 * @property string|null $balance_note
 * @property int $client_id
 */
trait HasBookkeepingTargetWorkflow
{
    /**
     * Day offsets from the Pick-Up date. Record lands one day after Pick-Up, and
     * Return and Payment follow one day apart, matching how the stages are
     * normally scheduled.
     *
     * @var array<string, int>
     */
    public const SEQUENCE_OFFSETS = [
        'pickup' => 0,
        'record' => 1,
        'return' => 2,
        'payment' => 3,
    ];

    public const SEQUENCE_LABELS = [
        'pickup' => 'Pick-Up',
        'record' => 'Record',
        'return' => 'Return',
        'payment' => 'Payment',
    ];

    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_WITH_BALANCE = 'with_balance';
    public const PAYMENT_STATUS_PENDING = 'pending';

    /** @var array<string, string> */
    public const PAYMENT_STATUSES = [
        self::PAYMENT_STATUS_PAID => 'Paid',
        self::PAYMENT_STATUS_WITH_BALANCE => 'With Balance',
        self::PAYMENT_STATUS_PENDING => 'Pending',
    ];

    /**
     * The plan relation is named `weeklyBookkeeping` in the weekly module and
     * `bookkeeping` in the monthly and quarterly ones.
     */
    public function planRelation(): string
    {
        return method_exists($this, 'bookkeeping') ? 'bookkeeping' : 'weeklyBookkeeping';
    }

    /**
     * The owning plan. Deliberately not named `plan`, because Eloquent would read
     * a method of that name as a relationship accessor and expect it to return a
     * relationship rather than a model.
     */
    public function planInstance()
    {
        return $this->{static::planRelation()};
    }

    public function planForeignKey(): string
    {
        return $this->{static::planRelation()}()->getForeignKeyName();
    }

    /**
     * The window a target date is allowed to sit in. Weekly plans carry an
     * explicit week range; the period modules derive theirs from BookkeepingPeriod.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function planWindow(): array
    {
        $plan = $this->planInstance();

        if ($plan === null) {
            $today = Carbon::today()->startOfDay();

            return [$today->copy()->startOfWeek(), $today->copy()->endOfWeek(Carbon::SUNDAY)];
        }

        /* A weekly plan holds its own range; the period modules derive theirs
           from a BookkeepingPeriod instance instead. */
        if (static::planRelation() === 'weeklyBookkeeping') {
            return [
                Carbon::parse($plan->week_start)->startOfDay(),
                Carbon::parse($plan->week_end)->endOfDay(),
            ];
        }

        return [
            Carbon::parse($plan->period()->startDate())->startOfDay(),
            Carbon::parse($plan->period()->endDate())->endOfDay(),
        ];
    }

    /**
     * The date this stage would be scheduled for, given an anchor date.
     *
     * The result is clamped to the last day of the plan's own window, because a
     * date past that point would be rejected by the existing "target date must
     * fall inside this week/month/quarter" rule. Nothing is written here; this is
     * the value a person confirms or overrides.
     */
    public function suggestedDateFor(string $taskType, Carbon|string|null $anchor): ?Carbon
    {
        if ($anchor === null || ! isset(static::SEQUENCE_OFFSETS[$taskType])) {
            return null;
        }

        [, $windowEnd] = static::planWindow();

        $date = Carbon::parse($anchor)->startOfDay()->addDays(static::SEQUENCE_OFFSETS[$taskType]);

        return $date->greaterThan($windowEnd) ? $windowEnd->copy()->startOfDay() : $date;
    }

    /**
     * The full Pick-Up → Record → Return → Payment schedule for this target's
     * client, keyed and ordered by task type.
     *
     * The keys are the point of this method, so the collection is deliberately
     * left keyed rather than re-indexed.
     *
     * @return Collection<string, self>
     */
    public function sequenceSiblings(): Collection
    {
        $foreignKey = static::planForeignKey();

        return static::query()
            ->where($foreignKey, $this->{$foreignKey})
            ->where('client_id', $this->client_id)
            ->whereIn('task_type', array_keys(static::SEQUENCE_OFFSETS))
            ->get()
            ->keyBy('task_type')
            ->sortBy(fn ($target, $taskType) => static::SEQUENCE_OFFSETS[$taskType]);
    }

    /**
     * Whether the current target date came from the sequence suggestion rather
     * than from a person. A NULL flag means the row predates the feature, and is
     * treated as manual so it is never silently rewritten.
     */
    public function dateIsAutomatic(): bool
    {
        return $this->target_date_auto === true;
    }

    /**
     * Move the automatically derived dates for this client's later stages to
     * match a new Pick-Up date.
     *
     * Three stages are deliberately left alone:
     *
     *  - a stage whose date was typed by a person, because overwriting it would
     *    destroy the manual override the feature is meant to protect;
     *  - a stage that already has actual work recorded, matching the existing
     *    rule that a target stops being editable once work starts;
     *  - any stage the acting user is not allowed to manage, so this never
     *    becomes a way to edit a colleague's task from a Pick-Up form.
     *
     * @param  callable|null  $mayEdit  receives a sibling target and returns whether it can be touched
     * @return list<string> the task types whose dates were refreshed
     */
    public function resequenceFrom(
        Carbon|string|null $pickupDate,
        ?callable $mayEdit = null,
    ): array {
        if ($pickupDate === null) {
            return [];
        }

        $refreshed = [];

        foreach (static::sequenceSiblings() as $taskType => $sibling) {
            if ($taskType === 'pickup' || ! $sibling->dateIsAutomatic()) {
                continue;
            }

            if (! $sibling->isPending()) {
                continue;
            }

            if ($mayEdit !== null && ! $mayEdit($sibling)) {
                continue;
            }

            $suggested = static::suggestedDateFor($taskType, $pickupDate);

            if ($suggested === null) {
                continue;
            }

            $sibling->forceFill([
                'target_date' => $suggested->format('Y-m-d'),
                'target_date_auto' => true,
            ])->save();

            $refreshed[] = $taskType;
        }

        return $refreshed;
    }

    public function hasBalance(): bool
    {
        return $this->payment_status === static::PAYMENT_STATUS_WITH_BALANCE
            || $this->balance_amount !== null;
    }

    public function balanceLabel(): ?string
    {
        if ($this->balance_amount === null) {
            return null;
        }

        return '₱'.number_format((float) $this->balance_amount, 2);
    }

    public function paymentStatusLabel(): ?string
    {
        if ($this->payment_status === null) {
            return null;
        }

        return static::PAYMENT_STATUSES[$this->payment_status] ?? null;
    }

    /**
     * A one-line summary for the compact tracker cells.
     */
    public function balanceSummary(): ?string
    {
        $parts = array_filter([$this->paymentStatusLabel(), $this->balanceLabel()]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
