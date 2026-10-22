<?php

namespace App\Support\Cash;

use App\Models\CashDailyPosition;
use App\Models\CashDailyReport;
use App\Models\CashDailyReportDay;
use App\Models\CashDailyReportLine;
use App\Models\CashDailyReportPayment;
use App\Models\CashPayment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Freezes a Cash Daily Position Report into its saved-report tables.
 *
 * The live worksheet (built in the controller) computes its days with a set of
 * rules: weekdays only, balances running forward across every calendar day,
 * weekend activity folded into the nearest weekday, beginning balance carried
 * forward when a day has no stored position, and overage/(shortage) against the
 * budget. This service reproduces those EXACT figures and writes them down as a
 * snapshot, so reopening the report never depends on the live data again.
 *
 * It also copies the salary / expense lines and the payments (with their true
 * paid_on dates and a link back to the original cash_payments row) so the saved
 * report is complete and auditable.
 */
class CashReportSnapshotter
{
    /**
     * Build the period's frozen day rows (same rules as the live worksheet)
     * without persisting anything. Returns an ordered list of day arrays.
     *
     * @return array<int, array<string, mixed>>
     */
    public function buildDays(User $client, CashReportPeriod $period): array
    {
        $positions = CashDailyPosition::query()
            ->where('client_id', $client->id)
            ->with('lines')
            ->whereDate('position_date', '>=', $period->startDate())
            ->whereDate('position_date', '<=', $period->endDate())
            ->get()
            ->keyBy(fn (CashDailyPosition $p) => $p->position_date->format('Y-m-d'));

        $payments = CashPayment::query()
            ->where('client_id', $client->id)
            ->whereDate('paid_on', '>=', $period->startDate())
            ->whereDate('paid_on', '<=', $period->endDate())
            ->orderBy('paid_on')
            ->get()
            ->groupBy(fn (CashPayment $p) => $p->paid_on->format('Y-m-d'));

        $days = [];
        $runningCash = null;
        $weekendCarry = collect();

        $cursor = Carbon::parse($period->startDate())->startOfDay();
        $lastDay = Carbon::parse($period->endDate())->startOfDay();

        for ($date = $cursor; $date->lte($lastDay); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $position = $positions->get($key);
            $dayPayments = $payments->get($key, collect());
            $isWeekend = $date->isWeekend();

            $beginning = $position !== null
                ? (float) $position->beginning_balance
                : ($runningCash ?? 0.0);
            // The ADD block: cash received, kept as `addition` lines.
            $collected = $position !== null ? $position->totalAdditions() : 0.0;
            $budget = $position !== null ? (float) $position->budget : 0.0;
            $salaries = $position !== null ? $position->totalSalaries() : 0.0;
            $expenses = $position !== null ? $position->totalExpenses() : 0.0;
            $less = $salaries + $expenses;
            // Ending balance = beginning + ADD − LESS.
            $ending = $beginning + $collected - $less;

            $runningCash = $ending;

            if ($isWeekend) {
                $weekendCarry->push([
                    'date' => $date->copy(),
                    'payments' => $dayPayments,
                    'position' => $position,
                    'collected' => $collected,
                    'salaries' => $salaries,
                    'expenses' => $expenses,
                    'less' => $less,
                ]);

                continue;
            }

            $carriedPayments = $weekendCarry->pluck('payments')->flatten(1);
            $carriedCollected = (float) $weekendCarry->sum('collected');
            $carriedSalaries = (float) $weekendCarry->sum('salaries');
            $carriedExpenses = (float) $weekendCarry->sum('expenses');
            $carriedLess = (float) $weekendCarry->sum('less');
            $carriedPositions = $weekendCarry->pluck('position')->filter();
            $weekendCarry = collect();

            $rowCollected = $collected + $carriedCollected;
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
                'payments' => $dayPayments->concat($carriedPayments)->values(),
                // Snapshot-only lines: the day's own line items plus any from a
                // folded-in weekend position, each keeping its source id.
                'lines' => collect([$position])->filter()
                    ->merge($carriedPositions)
                    ->flatMap(fn (CashDailyPosition $p) => $p->lines)
                    ->values(),
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

        return [
            'days' => $days,
            'trailing' => [
                'collected' => (float) $weekendCarry->sum('collected'),
                'salaries' => (float) $weekendCarry->sum('salaries'),
                'expenses' => (float) $weekendCarry->sum('expenses'),
                'less' => (float) $weekendCarry->sum('less'),
            ],
        ];
    }

    /**
     * Persist a frozen snapshot for one client + period. Must be called inside
     * a transaction by the controller; the unique (client_id, period_key)
     * constraint is the final guard against duplicates.
     */
    public function freeze(
        User $client,
        CashReportPeriod $period,
        ?User $actor,
        ?string $label = null,
        ?string $notes = null,
    ): CashDailyReport {
        $built = $this->buildDays($client, $period);
        $days = $built['days'];
        $trailing = $built['trailing'];

        $totalCollected = array_sum(array_column($days, 'collected')) + $trailing['collected'];
        $totalSalaries = array_sum(array_column($days, 'salaries')) + $trailing['salaries'];
        $totalExpenses = array_sum(array_column($days, 'expenses')) + $trailing['expenses'];
        $totalLess = array_sum(array_column($days, 'less')) + $trailing['less'];
        $totalBudget = array_sum(array_column($days, 'budget'));
        $totalOverShort = array_sum(array_column($days, 'over_short'));
        $beginning = $days[0]['beginning'] ?? 0.0;

        return DB::transaction(function () use (
            $client, $period, $actor, $label, $notes, $days,
            $totalCollected, $totalSalaries, $totalExpenses, $totalLess,
            $totalBudget, $totalOverShort, $beginning
        ) {
            $report = CashDailyReport::create([
                'client_id' => $client->id,
                'period_type' => $period->type,
                'year' => $period->year,
                'month' => $period->month,
                'week' => $period->week,
                'quarter' => $period->quarter,
                'period_start' => $period->startDate(),
                'period_end' => $period->endDate(),
                'period_key' => CashDailyReport::periodKey(
                    $period->type,
                    $period->year,
                    $period->month,
                    $period->week,
                    $period->quarter,
                ),
                'beginning_balance' => $beginning,
                'budget' => $totalBudget,
                'total_collected' => $totalCollected,
                'total_salaries' => $totalSalaries,
                'total_expenses' => $totalExpenses,
                'total_less' => $totalLess,
                'total_budget' => $totalBudget,
                'total_over_short' => $totalOverShort,
                'label' => $label,
                'notes' => $notes,
                'created_by' => $actor?->id,
            ]);

            foreach ($days as $day) {
                $dayRow = CashDailyReportDay::create([
                    'cash_daily_report_id' => $report->id,
                    'report_date' => $day['date']->format('Y-m-d'),
                    'beginning_balance' => $day['beginning'],
                    'collected' => $day['collected'],
                    'budget' => $day['budget'],
                    'salaries' => $day['salaries'],
                    'expenses' => $day['expenses'],
                    'less' => $day['less'],
                    'ending_balance' => $day['ending'],
                    'actual_cash' => $day['actual_cash'],
                    // The column is NOT NULL; an uncounted day keeps 0 here and
                    // is distinguished purely by a null actual_cash.
                    'over_short' => $day['over_short'] ?? 0,
                    'auto_beginning' => $day['auto_beginning'],
                    'has_weekend_fold' => $day['has_weekend_fold'],
                ]);

                foreach ($day['lines'] as $line) {
                    CashDailyReportLine::create([
                        'cash_daily_report_id' => $report->id,
                        'cash_daily_report_day_id' => $dayRow->id,
                        'type' => $line->type,
                        'category' => $line->category,
                        'label' => $line->label,
                        'amount' => $line->amount,
                        'notes' => $line->notes,
                        'source_line_id' => $line->id,
                    ]);
                }

                foreach ($day['payments'] as $payment) {
                    CashDailyReportPayment::create([
                        'cash_daily_report_id' => $report->id,
                        'cash_daily_report_day_id' => $dayRow->id,
                        'cash_payment_id' => $payment->id,
                        'paid_on' => $payment->paid_on?->format('Y-m-d'),
                        'amount' => $payment->amount,
                        'method' => $payment->method,
                        'status' => $payment->status,
                        'reference' => null,
                    ]);
                }
            }

            return $report;
        });
    }
}
