<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\CashDailyLine;
use App\Models\CashDailyPosition;
use App\Models\CashDailyReport;
use App\Models\CashDailyReportDay;
use App\Models\CashDailyReportLine;
use App\Models\CashDailyReportPayment;
use App\Models\User;
use App\Models\WeeklyBookkeeping;
use App\Models\WeeklyBookkeepingTarget;
use App\Support\Cash\PaymentSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Covers the saved (frozen) Cash Daily Position Report layer: creating a
 * report, the overview list, the per-report details modal, client isolation,
 * duplicate prevention, and the guarantee that reopening a report uses the
 * stored snapshot rather than recalculating from live data.
 */
class CashDailyReportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(string $name = 'Test Client'): User
    {
        return User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'name' => $name,
            'business_name' => "{$name} Business",
            'approved_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function paymentTarget(User $client, string $week, float $due = 1000.0): WeeklyBookkeepingTarget
    {
        $plan = WeeklyBookkeeping::create([
            'staff_id' => $this->admin()->id,
            'week_start' => $week,
            'week_end' => $week,
        ]);

        Billing::create([
            'client_id' => $client->id,
            'period_label' => 'TEST BILLING',
            'quarter' => 4,
            'year' => 2026,
            'total' => $due,
            'status' => Billing::STATUS_UNPAID,
        ]);

        return WeeklyBookkeepingTarget::create([
            'weekly_bookkeeping_id' => $plan->id,
            'client_id' => $client->id,
            'task_type' => 'payment',
            'target_date' => $week,
            'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
        ]);
    }

    private function saveReport(User $client, string $periodType, int $year, array $extra = [])
    {
        return $this->actingAs($this->admin())->post(route('admin.cash-daily-position.reports.store'), array_merge([
            'client_id' => $client->id,
            'period_type' => $periodType,
            'year' => $year,
        ], $extra));
    }

    // ---- Creation + snapshot integrity ----------------------------------

    public function test_an_admin_can_save_a_month_report_and_it_is_frozen(): void
    {
        $client = $this->client('Frozen Client');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);
        $payment = app(PaymentSynchronizer::class)->confirm($target, 1000.0, 'cash', '2026-10-05', $this->admin());

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 500,
            'budget' => 1200,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Client payment',
            'amount' => 1000,
            'source_cash_payment_id' => $payment->id,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_SALARY,
            'label' => 'Payroll',
            'amount' => 200,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_EXPENSE,
            'label' => 'Supplies',
            'amount' => 50,
        ]);

        $this->saveReport($client, 'month', 2026, ['month' => 10])
            ->assertRedirect()
            ->assertSessionHas('status');

        $report = CashDailyReport::firstOrFail();
        $this->assertSame($client->id, (int) $report->client_id);
        $this->assertSame('month', $report->period_type);
        $this->assertSame('m:2026-10', $report->period_key);
        $this->assertSame('2026-10-01', $report->period_start->format('Y-m-d'));
        $this->assertSame('2026-10-31', $report->period_end->format('Y-m-d'));
        $this->assertSame('1000.00', $report->total_collected);
        $this->assertSame('250.00', $report->total_less);
        $this->assertSame('1200.00', $report->total_budget);

        // The Oct 5 row: beginning 500, ADD 1000, LESS 250 → ending 1250.
        $day = CashDailyReportDay::whereDate('report_date', '2026-10-05')->firstOrFail();
        $this->assertSame('500.00', $day->beginning_balance);
        $this->assertSame('1250.00', $day->ending_balance);
        $this->assertSame('200.00', $day->salaries);
        $this->assertSame('50.00', $day->expenses);

        // Lines + payments were copied with audit references.
        $this->assertSame(3, CashDailyReportLine::count());
        $line = CashDailyReportLine::where('label', 'Payroll')->firstOrFail();
        $this->assertNotNull($line->source_line_id);

        $pay = CashDailyReportPayment::firstOrFail();
        $this->assertSame('2026-10-05', $pay->paid_on->format('Y-m-d'));
        $this->assertNotNull($pay->cash_payment_id);
    }

    public function test_a_week_report_uses_a_week_period_key_and_bounds(): void
    {
        $client = $this->client('Week Client');

        $this->saveReport($client, 'week', 2026, ['month' => 10, 'week' => 2])
            ->assertRedirect();

        $report = CashDailyReport::firstOrFail();
        $this->assertSame('w:2026-10-2', $report->period_key);
        $this->assertSame('2026-10-08', $report->period_start->format('Y-m-d'));
        $this->assertSame('2026-10-14', $report->period_end->format('Y-m-d'));
    }

    public function test_a_quarter_report_uses_a_quarter_period_key(): void
    {
        $client = $this->client('Quarter Client');

        $this->saveReport($client, 'quarter', 2026, ['quarter' => 4])->assertRedirect();

        $report = CashDailyReport::firstOrFail();
        $this->assertSame('q:2026-4', $report->period_key);
        $this->assertSame('2026-10-01', $report->period_start->format('Y-m-d'));
        $this->assertSame('2026-12-31', $report->period_end->format('Y-m-d'));
    }

    // ---- Duplicate prevention -------------------------------------------

    public function test_saving_the_same_client_and_period_twice_is_rejected(): void
    {
        $client = $this->client('Duplicate Client');

        $this->saveReport($client, 'month', 2026, ['month' => 10])->assertRedirect();
        $this->saveReport($client, 'month', 2026, ['month' => 10])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, CashDailyReport::count());
    }

    public function test_the_database_unique_constraint_blocks_a_duplicate_period_key(): void
    {
        $client = $this->client('Constraint Client');

        $this->saveReport($client, 'month', 2026, ['month' => 10])->assertRedirect();

        // Bypass the controller: the constraint itself must refuse the insert.
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('cash_daily_reports')->insert([
            'client_id' => $client->id,
            'period_type' => 'month',
            'year' => 2026,
            'month' => 10,
            'period_start' => '2026-10-01',
            'period_end' => '2026-10-31',
            'period_key' => 'm:2026-10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // ---- Frozen reopen (does not recalculate from live data) -------------

    public function test_reopening_a_report_uses_the_frozen_snapshot_not_live_data(): void
    {
        $client = $this->client('Reopen Client');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);
        $payment = app(PaymentSynchronizer::class)->confirm($target, 1000.0, 'cash', '2026-10-05', $this->admin());

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 500,
            'budget' => 1200,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Client payment',
            'amount' => 1000,
            'source_cash_payment_id' => $payment->id,
        ]);

        $this->saveReport($client, 'month', 2026, ['month' => 10])->assertRedirect();

        $report = CashDailyReport::firstOrFail();
        $this->assertSame('1000.00', $report->total_collected);

        // Change the live data AFTER saving: add a new payment and a line.
        $extraTarget = $this->paymentTarget($client, '2026-10-20', 5000.0);
        $extraPayment = app(PaymentSynchronizer::class)->confirm($extraTarget, 5000.0, 'cash', '2026-10-20', $this->admin());

        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Later payment',
            'amount' => 5000,
            'source_cash_payment_id' => $extraPayment->id,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_EXPENSE,
            'label' => 'New expense',
            'amount' => 999,
        ]);

        // The saved report must still show its frozen figures.
        $report->refresh();
        $this->assertSame('1000.00', $report->total_collected);
        $this->assertSame('0.00', $report->total_less);

        // And the overview must render the frozen total, not the new 6000.
        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index'))
            ->assertOk()
            ->assertSee('Recorded Cash Daily Position Reports')
            ->assertSee('1,000.00')
            ->assertDontSee('6,000.00');
    }

    public function test_the_saved_payment_keeps_its_true_weekend_date_and_audit_link(): void
    {
        $client = $this->client('Weekend Client');
        // 2026-10-04 is a Sunday — a payment that falls on a weekend.
        $target = $this->paymentTarget($client, '2026-10-04', 800.0);
        $payment = app(PaymentSynchronizer::class)->confirm($target, 800.0, 'cash', '2026-10-04', $this->admin());

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-04',
            'beginning_balance' => 0,
            'budget' => 0,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Sunday client payment',
            'amount' => 800,
            'source_cash_payment_id' => $payment->id,
        ]);

        $this->saveReport($client, 'month', 2026, ['month' => 10])->assertRedirect();

        $pay = CashDailyReportPayment::whereDate('paid_on', '2026-10-04')->firstOrFail();
        $this->assertSame('2026-10-04', $pay->paid_on->format('Y-m-d'));
        $this->assertNotNull($pay->cash_payment_id);

        // Sunday folds forward into the next weekday (Mon Oct 5).
        $day = CashDailyReportDay::whereDate('report_date', '2026-10-05')->firstOrFail();
        $this->assertTrue((bool) $day->has_weekend_fold);
        $this->assertSame('800.00', $day->collected);
    }

    // ---- Overview + modal ------------------------------------------------

    public function test_the_overview_lists_saved_reports_and_renders_a_modal_per_report(): void
    {
        $client = $this->client('Overview Client');
        $this->saveReport($client, 'month', 2026, ['month' => 10])->assertRedirect();

        $report = CashDailyReport::firstOrFail();

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index'))
            ->assertOk()
            ->assertSee('Recorded Cash Daily Position Reports')
            ->assertSee('Overview Client')
            ->assertSee('October 2026')
            ->assertSee('id="cdpReportModal'.$report->id.'"', false)
            ->assertSee('data-bs-target="#cdpReportModal'.$report->id.'"', false);
    }

    public function test_the_modal_shows_frozen_header_totals_and_daily_rows(): void
    {
        $client = $this->client('Modal Client');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);
        $payment = app(PaymentSynchronizer::class)->confirm($target, 1000.0, 'cash', '2026-10-05', $this->admin());

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 500,
            'budget' => 1200,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Client payment',
            'amount' => 1000,
            'source_cash_payment_id' => $payment->id,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_SALARY,
            'label' => 'Payroll',
            'amount' => 200,
        ]);

        $this->saveReport($client, 'month', 2026, ['month' => 10])->assertRedirect();

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index'))
            ->assertOk()
            ->assertSee('1,300.00')          // frozen ending on Oct 5: 500 + 1000 - 200
            ->assertSee('Payroll')            // frozen salary line
            ->assertSee('Ending balance');
    }

    // ---- Isolation + authorization --------------------------------------

    public function test_a_report_can_only_be_deleted_by_a_user_who_may_see_its_client(): void
    {
        $client = $this->client('Delete Client');
        $this->saveReport($client, 'month', 2026, ['month' => 10])->assertRedirect();
        $report = CashDailyReport::firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.cash-daily-position.reports.destroy', $report))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertSame(0, CashDailyReport::count());
        $this->assertSame(0, CashDailyReportDay::count());
    }

    public function test_only_snapshot_children_cascade_when_a_report_is_deleted(): void
    {
        $client = $this->client('Cascade Client');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);
        app(PaymentSynchronizer::class)->confirm($target, 1000.0, 'cash', '2026-10-05', $this->admin());

        $this->saveReport($client, 'month', 2026, ['month' => 10])->assertRedirect();
        $report = CashDailyReport::firstOrFail();

        $this->actingAs($this->admin())
            ->delete(route('admin.cash-daily-position.reports.destroy', $report))
            ->assertRedirect();

        // The original payment must survive — no cascade reached it.
        $this->assertSame(1, \App\Models\CashPayment::count());
    }

    public function test_a_guest_cannot_save_a_report(): void
    {
        $client = $this->client('Guest Client');

        $this->post(route('admin.cash-daily-position.reports.store'), [
            'client_id' => $client->id,
            'period_type' => 'month',
            'year' => 2026,
            'month' => 10,
        ])->assertRedirect(route('login'));

        $this->assertSame(0, CashDailyReport::count());
    }

    public function test_saving_with_an_unknown_client_id_is_rejected(): void
    {
        $this->saveReport($this->client('Scoped Client'), 'month', 2026, ['month' => 10])->assertRedirect();

        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.cash-daily-position.reports.store'), [
                'client_id' => $staff->id,
                'period_type' => 'month',
                'year' => 2026,
                'month' => 10,
            ])
            ->assertNotFound();
    }
}
