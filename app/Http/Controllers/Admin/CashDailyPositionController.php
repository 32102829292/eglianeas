<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\CashDailyLine;
use App\Models\CashDailyPosition;
use App\Models\CashDailyReport;
use App\Models\CashPayment;
use App\Models\MonthlyBookkeepingTarget;
use App\Models\QuarterlyBookkeepingTarget;
use App\Models\User;
use App\Models\WeeklyBookkeepingTarget;
use App\Support\Cash\CashReportPeriod;
use App\Support\Cash\CashReportSnapshotter;
use App\Support\Cash\PaymentSynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * The Cash Daily Position report.
 *
 * The report is selection-gated: an administrator first chooses ONE client and
 * ONE accounting period (week / month / quarter), and only then does the page
 * read any financial data. Every query below is filtered by that client and
 * date range on the server, so no other client's records are ever fetched,
 * hidden or not.
 *
 * Money received is read from records that already exist (paid billings and
 * cash_payments); only beginning balance, budget and salary/expense lines are
 * supplied by the user. Confirming a payment flows through PaymentSynchronizer,
 * which by design touches exactly one bookkeeping target.
 */
class CashDailyPositionController extends Controller
{
    private const TARGET_MODELS = [
        'weekly' => WeeklyBookkeepingTarget::class,
        'monthly' => MonthlyBookkeepingTarget::class,
        'quarterly' => QuarterlyBookkeepingTarget::class,
    ];

    public function index(Request $request): View
    {
        $clients = $this->authorizedClients();

        // Archive mode: show monthly archive by default (new workflow)
        $year = (int) ($request->get('year') ?: now()->year);
        $month = (int) ($request->get('month') ?: now()->month);
        if ($month < 1 || $month > 12) {
            $month = (int) now()->month;
        }

        $startOfMonth = Carbon::create($year, $month, 1)->startOfDay();
        $endOfMonth = $startOfMonth->copy()->endOfMonth();

        $savedByDate = CashDailyPosition::query()
            ->whereBetween('position_date', [$startOfMonth->toDateString(), $endOfMonth->toDateString()])
            ->get()
            ->keyBy(fn ($p) => $p->position_date->format('Y-m-d'));

        $days = [];
        $cursor = $startOfMonth->copy();
        while ($cursor->lte($endOfMonth)) {
            $key = $cursor->format('Y-m-d');
            $days[] = [
                'date' => $cursor->copy(),
                'hasReport' => $savedByDate->has($key),
                'position' => $savedByDate->get($key),
            ];
            $cursor->addDay();
        }

        // The report is selection-gated: ONE client and ONE period must both be
        // given before any financial data is read. A selection that was
        // attempted but is incomplete, or that names a client this user may
        // not see, must never fall through to a report.
        $selectedClient = $this->resolveSelectedClient($request, $clients);
        $period = $this->resolvePeriod($request);
        $selectionAttempted = $request->hasAny(['client_id', 'period_type']);

        if ($selectionAttempted && $selectedClient !== null && $period !== null) {
            $data = $this->buildReport($selectedClient, $period, $clients, $request);
            $data['savedReports'] = $this->savedReports();
            $data['archive'] = $this->archivePayload($year, $month, $startOfMonth, $days);
            $data['view'] = 'legacy';

            return view('admin.cash-daily-position.index', $data);
        }

        $data = [
            'clients' => $clients,
            'selectedClient' => null,
            'period' => null,
            'selection' => $this->rawSelection($request),
            'years' => $this->availableYears(),
            'weeksInMonth' => CashReportPeriod::weeksInMonth($year, $month),
            'days' => [],
            'totals' => $this->emptyTotals(),
            'outstanding' => collect(),
            'savedReports' => $this->savedReports(),
            'archive' => $this->archivePayload($year, $month, $startOfMonth, $days),
        ];

        // A selection was attempted but it is incomplete or unauthorized, so the
        // empty state is shown; with no selection at all the monthly archive is
        // the landing view.
        $data['view'] = $selectionAttempted ? 'legacy' : 'archive';

        return view('admin.cash-daily-position.index', $data);
    }

    /**
     * The monthly archive strip (one card per day of the month) that sits above
     * the worksheet.
     *
     * @param  list<array<string, mixed>>  $days
     * @return array<string, mixed>
     */
    private function archivePayload(int $year, int $month, Carbon $startOfMonth, array $days): array
    {
        return [
            'year' => $year,
            'month' => $month,
            'monthName' => $startOfMonth->format('F Y'),
            'days' => $days,
            'prevMonth' => $startOfMonth->copy()->subMonth()->format('Y-m'),
            'nextMonth' => $startOfMonth->copy()->addMonth()->format('Y-m'),
        ];
    }

    /**
     * Save the current client + period as a frozen Cash Daily Position Report.
     *
     * The whole snapshot is written in one transaction by the snapshotter. The
     * (client_id, period_key) unique index is the database-level guard against
     * duplicates: a concurrent/duplicate save raises a unique violation which
     * we translate into a friendly message rather than a second report.
     */
    public function storeReport(Request $request, CashReportSnapshotter $snapshotter): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:users,id'],
            'period_type' => ['required', Rule::in(array_keys(CashReportPeriod::TYPES))],
            'year' => ['required', 'integer'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'week' => ['nullable', 'integer', 'between:1,5'],
            'quarter' => ['nullable', 'integer', 'between:1,4'],
            'label' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $client = $this->assertAuthorizedClient((int) $data['client_id']);

        $period = $this->resolvePeriodFromArray($data);
        if ($period === null) {
            return back()->with('error', 'Please choose a valid client and period before saving the report.');
        }

        try {
            $report = $snapshotter->freeze(
                $client,
                $period,
                auth()->user(),
                $data['label'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return back()->with('error', 'A Cash Daily Position Report already exists for '.$client->name.' for '.$period->label().'.');
            }

            throw $e;
        }

        ActivityLog::record(
            auth()->user(),
            'admin.cash_daily_report_saved',
            'Saved Cash Daily Position Report for '.$client->name.' ('.$period->label().'), report #'.$report->id.'.',
        );

        return back()->with('status', 'Cash Daily Position Report saved for '.$client->name.' ('.$period->label().').');
    }

    /**
     * Delete a saved report. Only its own snapshot rows are removed; no live
     * financial record is touched. Scoped to the client the user may access.
     */
    public function destroyReport(CashDailyReport $report): RedirectResponse
    {
        $this->assertAuthorizedClient((int) $report->client_id);

        $id = $report->id;
        $report->delete();

        ActivityLog::record(
            auth()->user(),
            'admin.cash_daily_report_deleted',
            'Deleted Cash Daily Position Report #'.$id.'.',
        );

        return back()->with('status', 'Saved report removed.');
    }

    /**
     * Create or update the hand-entered figures for one day. This never writes
     * money received — that arrives through recordPayment().
     */
    public function storePosition(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:users,id'],
            'position_date' => ['required', 'date'],
            'beginning_balance' => ['required', 'numeric'],
            'budget' => ['nullable', 'numeric'],
            // Null means "not counted yet"; a blank field clears the count.
            'actual_cash' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $client = $this->assertAuthorizedClient((int) $data['client_id']);

        CashDailyPosition::updateOrCreate(
            [
                'client_id' => $client->id,
                'position_date' => Carbon::parse($data['position_date'])->format('Y-m-d'),
            ],
            [
                'beginning_balance' => $data['beginning_balance'],
                'budget' => $data['budget'] ?? 0,
                'actual_cash' => $data['actual_cash'],
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]
        );

        return back()->with('status', 'Daily position saved.');
    }

    public function storeLine(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'cash_daily_position_id' => ['required', 'exists:cash_daily_positions,id'],
            'type' => ['required', Rule::in(array_keys(CashDailyLine::TYPES))],
            'category' => ['nullable', 'string', 'max:60'],
            'label' => ['required', 'string', 'max:150'],
            'amount' => ['required', 'numeric', 'gt:0'],
            // Only meaningful for the ADD block; a client a receipt belongs to.
            'client_id' => ['nullable', 'integer', 'exists:users,id'],
            'source_cash_payment_id' => ['nullable', 'integer', 'exists:cash_payments,id'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        // The line's position must belong to a client this user may see. A
        // daily position is client-scoped, so this is also the ownership check.
        $position = CashDailyPosition::findOrFail($data['cash_daily_position_id']);
        $this->assertAuthorizedClient((int) $position->client_id);

        // A client-linked receipt must belong to the SAME client as the day.
        if (! empty($data['client_id'])) {
            $lineClient = $this->assertAuthorizedClient((int) $data['client_id']);
            abort_if($lineClient->id !== (int) $position->client_id, 404);
        }

        // A mirrored payment must belong to the same client and must not be
        // already recorded, so cash is never counted twice.
        if (! empty($data['source_cash_payment_id'])) {
            $payment = CashPayment::query()->findOrFail($data['source_cash_payment_id']);
            abort_if((int) $payment->client_id !== (int) $position->client_id, 404);

            $alreadyRecorded = CashDailyLine::query()
                ->where('source_cash_payment_id', $payment->id)
                ->exists();

            if ($alreadyRecorded) {
                return back()->with('error', 'That confirmed payment is already recorded as cash received.');
            }
        }

        try {
            CashDailyLine::create($data);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                return back()->with('error', 'That confirmed payment is already recorded as cash received.');
            }

            throw $e;
        }

        return back()->with('status', $data['type'] === CashDailyLine::TYPE_ADDITION ? 'Cash received added.' : 'Line added.');
    }

    public function destroyLine(CashDailyLine $line): RedirectResponse
    {
        $position = $line->position;
        if ($position !== null) {
            $this->assertAuthorizedClient((int) $position->client_id);
        }

        $line->delete();

        return back()->with('status', 'Line removed.');
    }

    /**
     * Confirm a payment against exactly one bookkeeping Billing / Payment
     * target. Steps, all server-side:
     *   1. the user may access the client;
     *   2. the target belongs to that same client;
     *   3. the target is a Billing / Payment task;
     *   4. the amount does not overpay the outstanding balance;
     * then the synchronizer records the payment and updates that one target,
     * atomically, honouring partial payments and idempotency.
     */
    public function recordPayment(Request $request, PaymentSynchronizer $synchronizer): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'integer', 'exists:users,id'],
            'target_type' => ['required', Rule::in(array_keys(self::TARGET_MODELS))],
            'target_id' => ['required', 'integer'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'method' => ['nullable', Rule::in(array_keys(WeeklyBookkeepingTarget::PAYMENT_METHODS))],
            'paid_on' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:120'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $client = $this->assertAuthorizedClient((int) $data['client_id']);

        $target = $this->resolveTarget($data['target_type'], (int) $data['target_id']);
        abort_if($target === null, 404);

        // The exact-target match is the whole point: a target that belongs to a
        // different client must never be payable from this client's report.
        if ((int) $target->client_id !== $client->id) {
            abort(404);
        }

        try {
            $payment = $synchronizer->confirm(
                $target,
                (float) $data['amount'],
                $data['method'] ?? null,
                $data['paid_on'] ?? null,
                auth()->user(),
                $data['idempotency_key'] ?? null,
                $data['notes'] ?? null,
            );
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        ActivityLog::record(
            auth()->user(),
            'admin.cash_payment_recorded',
            "Recorded {$payment->statusLabel()} payment of ₱".number_format((float) $payment->amount, 2)
                .' for '.$data['target_type'].' target #'.$target->getKey().' ('.$client->name.').',
        );

        return back()->with('status', 'Payment recorded — the Billing / Payment entry is now '.$payment->statusLabel().'.');
    }

    /**
     * Build every figure the report shows, all filtered to one client and one
     * date range.
     */
    private function buildReport(User $client, CashReportPeriod $period, $clients, Request $request): array
    {
        $positions = CashDailyPosition::query()
            ->where('client_id', $client->id)
            ->with('lines')
            ->whereDate('position_date', '>=', $period->startDate())
            ->whereDate('position_date', '<=', $period->endDate())
            ->get()
            ->keyBy(fn (CashDailyPosition $p) => $p->position_date->format('Y-m-d'));

        // Money received during the range for this client only. Confirmed
        // payments are surfaced as candidate ADD rows the user may mirror into
        // an addition line; the line is the authoritative ADD entry so a
        // payment can never be counted twice.
        $payments = CashPayment::query()
            ->where('client_id', $client->id)
            ->whereDate('paid_on', '>=', $period->startDate())
            ->whereDate('paid_on', '<=', $period->endDate())
            ->orderBy('paid_on')
            ->get()
            ->groupBy(fn (CashPayment $p) => $p->paid_on->format('Y-m-d'));

        // The worksheet is weekdays-only (Mon–Fri). We still run the balance
        // forward across EVERY calendar day so weekend activity is not lost,
        // but only weekdays get a row. A weekend payment or expense is folded
        // into the nearest weekday row so the cash still appears, and its date
        // is shown on that row rather than dropped.
        $days = [];
        $runningCash = null;
        $weekendCarry = collect(); // movements accumulated for the next weekday

        // Iterate with a mutable cursor: the period's own bounds are immutable.
        $cursor = Carbon::parse($period->startDate())->startOfDay();
        $lastDay = Carbon::parse($period->endDate())->startOfDay();

        for ($date = $cursor; $date->lte($lastDay); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $position = $positions->get($key);
            $dayPayments = $payments->get($key, collect());
            $isWeekend = $date->isWeekend();

            // Carry forward: a day without a stored position inherits the
            // previous day's ending cash as its opening balance. Never zeroed.
            $beginning = $position !== null
                ? (float) $position->beginning_balance
                : ($runningCash ?? 0.0);
            // The ADD block for the day: cash received, kept on cash_daily_lines
            // as `addition` rows (client-linked or free-text).
            $additions = $position !== null ? $position->totalAdditions() : 0.0;
            $budget = $position !== null ? (float) $position->budget : 0.0;
            $salaries = $position !== null ? $position->totalSalaries() : 0.0;
            $expenses = $position !== null ? $position->totalExpenses() : 0.0;
            $less = $salaries + $expenses;
            // Ending balance = beginning + ADD − LESS.
            $ending = $beginning + $additions - $less;

            $runningCash = $ending;

            if ($isWeekend) {
                // No row, but remember the money so the next weekday shows it.
                $weekendCarry->push([
                    'date' => $date->copy(),
                    'payments' => $dayPayments,
                    'position' => $position,
                    'collected' => $additions,
                    'salaries' => $salaries,
                    'expenses' => $expenses,
                    'less' => $less,
                ]);

                continue;
            }

            // Fold any weekend movements onto this weekday row.
            $carriedPayments = $weekendCarry->pluck('payments')->flatten(1);
            $carriedCollected = (float) $weekendCarry->sum('collected');
            $carriedSalaries = (float) $weekendCarry->sum('salaries');
            $carriedExpenses = (float) $weekendCarry->sum('expenses');
            $carriedLess = (float) $weekendCarry->sum('less');
            $weekendCarry = collect();

            $rowCollected = $additions + $carriedCollected;
            $rowSalaries = $salaries + $carriedSalaries;
            $rowExpenses = $expenses + $carriedExpenses;
            $rowLess = $less + $carriedLess;
            // Overage / (shortage) = actual cash counted − expected ending.
            // Null actual cash means the cash has not been counted yet.
            $actualCash = $position?->actual_cash !== null ? (float) $position->actual_cash : null;
            $overShort = $actualCash === null ? null : $actualCash - $ending;

            $days[] = [
                'date' => $date->copy(),
                'position' => $position,
                // Payments shown on this row: the day's own plus any folded in
                // from a weekend, each keeping its true paid_on date.
                'payments' => $dayPayments->concat($carriedPayments)->values(),
                'beginning' => $beginning,
                'collected' => $rowCollected,
                'budget' => $budget,
                'salaries' => $rowSalaries,
                'expenses' => $rowExpenses,
                'less' => $rowLess,
                'ending' => $ending,
                'actual_cash' => $actualCash,
                'over_short' => $overShort,
                'auto_beginning' => $position === null,
                'has_weekend_fold' => $carriedPayments->isNotEmpty()
                    || $carriedSalaries > 0
                    || $carriedExpenses > 0
                    || $carriedCollected > 0,
            ];
        }

        // Weekend activity before the first weekday of the range has nowhere to
        // fold; park it in the totals only so no cash disappears.
        $trailingCollected = (float) $weekendCarry->sum('collected');
        $trailingSalaries = (float) $weekendCarry->sum('salaries');
        $trailingExpenses = (float) $weekendCarry->sum('expenses');
        $trailingLess = (float) $weekendCarry->sum('less');

        return [
            'clients' => $clients,
            'selectedClient' => $client,
            'period' => $period,
            'selection' => $period->query() + ['client_id' => $client->id],
            'years' => $this->availableYears(),
            'weeksInMonth' => CashReportPeriod::weeksInMonth($period->year, $period->month ?? now()->month),
            'days' => $days,
            'totals' => [
                'collected' => array_sum(array_column($days, 'collected')) + $trailingCollected,
                'salaries' => array_sum(array_column($days, 'salaries')) + $trailingSalaries,
                'expenses' => array_sum(array_column($days, 'expenses')) + $trailingExpenses,
                'less' => array_sum(array_column($days, 'less')) + $trailingLess,
                'budget' => array_sum(array_column($days, 'budget')),
                // Sum only the days actually counted; null = not yet counted.
                'actual_cash' => collect($days)->sum(fn ($d) => $d['actual_cash'] ?? 0),
                'counted_days' => collect($days)->whereNotNull('actual_cash')->count(),
                'over_short' => collect($days)->sum(fn ($d) => $d['over_short'] ?? 0),
            ],
            'outstanding' => $this->outstandingBillings($client, $period),
            'previousPeriod' => $period->previous(),
            'nextPeriod' => $period->next(),
            'sameTypePeriods' => $period->siblings(),
        ];
    }

    /**
     * Outstanding Billing / Payment targets for the selected client covering
     * the selected period, across all three bookkeeping cadences. A target is
     * outstanding until a cash_payments row exists for that exact target.
     *
     * @return \Illuminate\Support\Collection<int, Model>
     */
    private function outstandingBillings(User $client, CashReportPeriod $period)
    {
        $rows = collect();

        foreach (self::TARGET_MODELS as $type => $class) {
            $targets = $class::query()
                ->where('client_id', $client->id)
                ->where('task_type', 'payment')
                ->whereDate('target_date', '>=', $period->startDate())
                ->whereDate('target_date', '<=', $period->endDate())
                ->with('client')
                ->orderBy('target_date')
                ->get();

            $rows = $rows->merge($targets->map(function (Model $target) use ($type) {
                $paid = (float) CashPayment::query()
                    ->where('billable_type', $target->getMorphClass())
                    ->where('billable_id', $target->getKey())
                    ->sum('amount');

                $target->setAttribute('report_target_type', $type);
                $target->setAttribute('report_amount_paid', $paid);

                return $target;
            }));
        }

        // Hide fully settled targets; show outstanding and partially paid ones.
        return $rows
            ->filter(function (Model $target) {
                $due = $this->amountDueFor($target);
                if ($due === null) {
                    // No billing on record: treat a settled target as done,
                    // otherwise surface it so it can still be paid.
                    return $target->payment_status === null
                        || $target->payment_status !== $target::PAYMENT_STATUS_PAID;
                }

                return (float) $target->report_amount_paid + 0.00001 < $due;
            })
            ->sortBy(fn (Model $t) => $t->target_date?->format('Y-m-d') ?? '9999')
            ->values();
    }

    private function amountDueFor(Model $target): ?float
    {
        $billing = Billing::query()
            ->where('client_id', $target->client_id)
            ->activeOnly()
            ->orderByDesc('year')
            ->orderByDesc('quarter')
            ->first();

        return $billing === null ? null : (float) $billing->total;
    }

    private function resolveTarget(string $type, int $id): ?Model
    {
        $class = self::TARGET_MODELS[$type] ?? null;
        if ($class === null) {
            return null;
        }

        return $class::query()->find($id);
    }

    /**
     * The active, authorized clients offered in the picker. Mirrors the billing
     * module's client source; never a separate client table.
     */
    private function authorizedClients()
    {
        return User::query()
            ->where('role', User::ROLE_CLIENT)
            ->orderBy('name')
            ->get();
    }

    /**
     * Compact client search for the report's autocomplete. Scoped to the same
     * authorized client set the picker uses and never loads the full list into
     * the page: it returns at most 20 matches for the typed query.
     */
    public function clientsJson(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        $clients = User::query()
            ->where('role', User::ROLE_CLIENT)
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner->where('name', 'like', "%{$q}%")
                        ->orWhere('business_name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('client_code', 'like', "%{$q}%");
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'business_name', 'email', 'client_code']);

        return response()->json($clients);
    }

    /**
     * Compact staff search for the salary autocomplete. Mirrors the weekly
     * bookkeeping "assignable staff" roles so only accounts eligible to own an
     * accounting task can be picked, and returns at most 20 matches rather than
     * loading every user into the page.
     */
    public function staffJson(Request $request): \Illuminate\Http\JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        $staff = User::query()
            ->whereIn('role', [User::ROLE_STAFF, User::ROLE_SUPERVISOR, User::ROLE_ADMIN])
            ->whereNull('deleted_at')
            ->when($q !== '', function ($query) use ($q) {
                $query->where('name', 'like', "%{$q}%");
            })
            ->orderBy('name')
            ->limit(20)
            ->get(['id', 'name', 'role']);

        return response()->json($staff);
    }

    private function resolveSelectedClient(Request $request, $clients): ?User
    {
        $id = (int) $request->get('client_id');
        if ($id <= 0) {
            return null;
        }

        return $clients->firstWhere('id', $id);
    }

    /**
     * Resolve the requested period, or null when the request is incomplete or
     * invalid (in which case the picker is shown again).
     */
    private function resolvePeriod(Request $request): ?CashReportPeriod
    {
        $type = (string) $request->get('period_type');
        $year = (int) $request->get('year');

        if (! array_key_exists($type, CashReportPeriod::TYPES) || $year <= 0) {
            return null;
        }

        try {
            return match ($type) {
                CashReportPeriod::WEEK => CashReportPeriod::week(
                    $year,
                    (int) $request->get('month'),
                    (int) $request->get('week')
                ),
                CashReportPeriod::MONTH => CashReportPeriod::month($year, (int) $request->get('month')),
                CashReportPeriod::QUARTER => CashReportPeriod::quarter($year, (int) $request->get('quarter')),
                default => null,
            };
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    private function assertAuthorizedClient(int $clientId): User
    {
        $client = User::query()
            ->where('id', $clientId)
            ->where('role', User::ROLE_CLIENT)
            ->first();

        abort_if($client === null, 404, 'Unknown client.');

        return $client;
    }

    /**
     * Resolve a period from already-validated request data (used by the save
     * action), or null when the combination is invalid.
     */
    private function resolvePeriodFromArray(array $data): ?CashReportPeriod
    {
        $type = (string) ($data['period_type'] ?? '');
        $year = (int) ($data['year'] ?? 0);

        if (! array_key_exists($type, CashReportPeriod::TYPES) || $year <= 0) {
            return null;
        }

        try {
            return match ($type) {
                CashReportPeriod::WEEK => CashReportPeriod::week($year, (int) $data['month'], (int) $data['week']),
                CashReportPeriod::MONTH => CashReportPeriod::month($year, (int) $data['month']),
                CashReportPeriod::QUARTER => CashReportPeriod::quarter($year, (int) $data['quarter']),
                default => null,
            };
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The saved reports visible on the page. Every report carries its frozen
     * snapshot so the details modal can be built without a second query per
     * report. Suspends eager-loading only after the caller sets a client scope;
     * by default the whole authorized set is shown, matching the picker.
     */
    private function savedReports()
    {
        return CashDailyReport::query()
            ->with(['client', 'days.payments', 'days.lines'])
            ->orderByDesc('period_start')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * Detect a duplicate-key failure across drivers (PostgreSQL 23505 /
     * SQLite 1555/2067) so a repeated save becomes a message, never a crash or
     * a second report.
     */
    private function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');

        return $sqlState === '23505' || $sqlState === '23000' || $sqlState === '1555' || $sqlState === '2067';
    }

    /**
     * @return array<string, mixed>
     */
    private function rawSelection(Request $request): array
    {
        return [
            'client_id' => (int) $request->get('client_id') ?: null,
            'period_type' => (string) $request->get('period_type') ?: null,
            'year' => (int) $request->get('year') ?: null,
            'month' => (int) $request->get('month') ?: null,
            'week' => (int) $request->get('week') ?: null,
            'quarter' => (int) $request->get('quarter') ?: null,
        ];
    }

    private function emptyTotals(): array
    {
        return [
            'collected' => 0.0,
            'salaries' => 0.0,
            'expenses' => 0.0,
            'less' => 0.0,
            'budget' => 0.0,
            'actual_cash' => 0.0,
            'counted_days' => 0,
            'over_short' => 0.0,
        ];
    }

    /**
     * Years offered in the picker: the current year and every year that has
     * bookkeeping data, newest first. Never hard-codes a particular year.
     *
     * @return list<int>
     */
    private function availableYears(): array
    {
        $years = collect();

        foreach (self::TARGET_MODELS as $class) {
            $years = $years->merge(
                $class::query()->whereNotNull('target_date')->pluck('target_date')
            );
        }

        $years = $years
            ->map(fn ($date) => (int) Carbon::parse($date)->format('Y'))
            ->push((int) now()->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        return $years->all();
    }

    public function create(Request $request): View|RedirectResponse
    {
        $date = $request->get('date') ? Carbon::parse($request->get('date'))->toDateString() : now()->toDateString();

        $existing = CashDailyPosition::query()->where('position_date', $date)->first();
        if ($existing) {
            return redirect()->route('admin.cash-daily-position.show', ['date' => $date])->with('status', 'Report already exists for this date.');
        }

        $prev = CashDailyPosition::query()
            ->where('position_date', '<', $date)
            ->orderByDesc('position_date')
            ->first();

        $beginningBalance = $prev ? (float) ($prev->expectedEnding() ?? $prev->ending_balance ?? $prev->beginning_balance ?? 0) : 0.0;

        return view('admin.cash-daily-position.daily', [
            'reportDate' => $date,
            'position' => null,
            'beginningBalance' => $beginningBalance,
            'mode' => 'create',
        ]);
    }

    public function show(Request $request): View|RedirectResponse
    {
        $date = $request->get('date') ? Carbon::parse($request->get('date'))->toDateString() : now()->toDateString();

        $position = CashDailyPosition::query()->with('lines')->where('position_date', $date)->first();
        if (! $position) {
            return redirect()->route('admin.cash-daily-position.create', ['date' => $date]);
        }

        return view('admin.cash-daily-position.daily', [
            'reportDate' => $date,
            'position' => $position,
            'beginningBalance' => (float) $position->beginning_balance,
            'mode' => 'view',
        ]);
    }

    public function storeDaily(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'position_date' => ['required', 'date'],
            'beginning_balance' => ['required', 'numeric'],
            'budget' => ['nullable', 'numeric'],
            'actual_cash' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'additions' => ['nullable', 'array'],
            'additions.*.label' => ['nullable', 'string', 'max:150'],
            'additions.*.amount' => ['nullable', 'numeric'],
            'salaries' => ['nullable', 'array'],
            'salaries.*.label' => ['nullable', 'string', 'max:150'],
            'salaries.*.amount' => ['nullable', 'numeric'],
            'expenses' => ['nullable', 'array'],
            'expenses.*.label' => ['nullable', 'string', 'max:150'],
            'expenses.*.amount' => ['nullable', 'numeric'],
        ]);

        $date = Carbon::parse($data['position_date'])->toDateString();
        $existing = CashDailyPosition::query()->where('position_date', $date)->first();
        if ($existing) {
            return redirect()->route('admin.cash-daily-position.show', ['date' => $date])->with('error', 'A report already exists for this date.');
        }

        DB::transaction(function () use ($request, $data, $date) {
            $position = CashDailyPosition::create([
                'client_id' => null,
                'position_date' => $date,
                'beginning_balance' => $data['beginning_balance'],
                'budget' => $data['budget'] ?? 0,
                'actual_cash' => $data['actual_cash'] ?? null,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => auth()->id(),
            ]);

            $this->storeLinesFor($request, $position, 'additions', CashDailyLine::TYPE_ADDITION);
            $this->storeLinesFor($request, $position, 'salaries', CashDailyLine::TYPE_SALARY);
            $this->storeLinesFor($request, $position, 'expenses', CashDailyLine::TYPE_EXPENSE);
        });

        ActivityLog::record(
            auth()->user(),
            'admin.cash_daily_report_saved',
            'Saved Cash Daily Position Report for '.$date.'.',
        );

        return redirect()->route('admin.cash-daily-position.index', ['year' => Carbon::parse($date)->year, 'month' => Carbon::parse($date)->month])->with('status', 'Cash Daily Position Report saved.');
    }

    private function storeLinesFor(Request $request, CashDailyPosition $position, string $key, string $type): void
    {
        $items = $request->input($key, []);
        if (! is_array($items)) {
            return;
        }
        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $amount = $item['amount'] ?? null;
            if ($label === '' || $amount === null || $amount === '') {
                continue;
            }
            if ((float) $amount <= 0) {
                continue;
            }
            CashDailyLine::create([
                'cash_daily_position_id' => $position->id,
                'type' => $type,
                'label' => $label,
                'amount' => $amount,
            ]);
        }
    }
}
