<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\CashDailyLine;
use App\Models\CashDailyPosition;
use App\Models\CashPayment;
use App\Models\User;
use App\Models\WeeklyBookkeeping;
use App\Models\WeeklyBookkeepingTarget;
use App\Support\Cash\CashReportPeriod;
use App\Support\Cash\PaymentSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Covers the redesigned Cash Daily Position report: the selection gate, the
 * period resolver, client isolation, and the exact-target payment guarantee.
 */
class CashDailyPositionTest extends TestCase
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

    private function plan(string $week): WeeklyBookkeeping
    {
        return WeeklyBookkeeping::create([
            'staff_id' => $this->admin()->id,
            'week_start' => $week,
            'week_end' => $week,
        ]);
    }

    private function paymentTarget(User $client, string $week, float $due = 1000.0): WeeklyBookkeepingTarget
    {
        $plan = $this->plan($week);

        if ($due > 0) {
            Billing::create([
                'client_id' => $client->id,
                'period_label' => 'TEST BILLING',
                'quarter' => 1,
                'year' => 2026,
                'total' => $due,
                'status' => Billing::STATUS_UNPAID,
            ]);
        }

        return WeeklyBookkeepingTarget::create([
            'weekly_bookkeeping_id' => $plan->id,
            'client_id' => $client->id,
            'task_type' => 'payment',
            'target_date' => $week,
            'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
        ]);
    }

    // ---- Period resolver -------------------------------------------------

    public function test_a_month_resolves_to_its_first_and_last_calendar_day(): void
    {
        $period = CashReportPeriod::month(2026, 10);

        $this->assertSame('2026-10-01', $period->startDate());
        $this->assertSame('2026-10-31', $period->endDate());
    }

    public function test_the_fourth_quarter_resolves_to_october_through_december(): void
    {
        $period = CashReportPeriod::quarter(2026, 4);

        $this->assertSame('2026-10-01', $period->startDate());
        $this->assertSame('2026-12-31', $period->endDate());
    }

    public function test_week_calculation_stays_within_its_month_across_years(): void
    {
        $secondWeek = CashReportPeriod::week(2026, 3, 2);
        $this->assertSame('2026-03-08', $secondWeek->startDate());
        $this->assertSame('2026-03-14', $secondWeek->endDate());

        $febWeek = CashReportPeriod::week(2024, 2, 5);
        $this->assertSame('2024-02-29', $febWeek->startDate());
        $this->assertSame('2024-02-29', $febWeek->endDate());
    }

    public function test_a_month_without_a_valid_fifth_week_reports_only_four(): void
    {
        $this->assertSame(5, CashReportPeriod::weeksInMonth(2026, 10));
        $this->assertSame(4, CashReportPeriod::weeksInMonth(2026, 2));
    }

    public function test_an_invalid_fifth_week_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        CashReportPeriod::week(2026, 2, 5);
    }

    // ---- Selection gate --------------------------------------------------

    public function test_opening_the_page_without_parameters_shows_the_selection_form(): void
    {
        $this->client('Some Client');

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index'))
            ->assertOk()
            ->assertSee('Add Cash Daily Position Report')
            ->assertDontSee('Outstanding Billing / Payment �?"');
    }

    public function test_an_incomplete_selection_does_not_load_a_report(): void
    {
        $client = $this->client('Incomplete Client');

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', ['client_id' => $client->id, 'period_type' => 'month']))
            ->assertOk()
            ->assertSee('No worksheet open');
    }

    public function test_a_valid_month_selection_loads_the_report(): void
    {
        $client = $this->client('Valid Client');

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Valid Client')
            ->assertSee('October 2026')
            ->assertSee('Oct 1, 2026 – Oct 31, 2026');
    }

    // ---- Client isolation ------------------------------------------------

    public function test_selecting_one_client_never_shows_another_clients_records(): void
    {
        $fina = $this->client('FINA SARI-SARI STORE');
        $samuel = $this->client('Samuel and Irene Consumer Goods Trading');

        $this->paymentTarget($fina, '2026-10-07', 1000.0);
        // A distinctive amount that only Samuel's report could ever show.
        $this->paymentTarget($samuel, '2026-10-14', 987654.0);

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $fina->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('FINA SARI-SARI STORE')
            // Samuel's report data (his billing amount) must never be fetched.
            ->assertDontSee('987654')
            ->assertDontSee('987,654.00');
    }

    public function test_an_unauthorized_client_id_is_rejected(): void
    {
        $staff = User::factory()->create([
            'role' => User::ROLE_STAFF,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $staff->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('No worksheet open');
    }

    // ---- Daily worksheet calc -------------------------------------------

    public function test_daily_totals_flow_from_additions_and_lines_for_the_selected_client(): void
    {
        $client = $this->client('Worksheet Client');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);

        $payment = app(PaymentSynchronizer::class)->confirm($target, 1000.0, 'cash', '2026-10-05', $this->admin());

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 500,
            'budget' => 1200,
        ]);

        // The ADD block is a cash-received line, mirrored from the confirmed
        // payment so it is counted once, not from the payment directly.
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

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('1,250.00')   // ending balance: 500 + 1000 - 250
            ->assertSee('250.00');    // LESS total
    }

    public function test_a_day_without_a_position_carries_the_previous_ending_cash_forward(): void
    {
        $client = $this->client('Carry Client');

        CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-01',
            'beginning_balance' => 400,
            'budget' => 400,
        ]);

        $period = CashReportPeriod::month(2026, 10);
        $this->assertTrue($period->contains('2026-10-01'));

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('400.00');
    }

    // ---- Payment synchronization ----------------------------------------

    public function test_recording_a_full_payment_marks_only_the_matching_target_paid(): void
    {
        $client = $this->client('Alpha');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);

        $payment = app(PaymentSynchronizer::class)->confirm($target, 1000.0, 'cash', '2026-10-06', $this->admin());

        $target->refresh();

        $this->assertSame(CashPayment::STATUS_PAID, $payment->status);
        $this->assertSame(WeeklyBookkeepingTarget::PAYMENT_STATUS_PAID, $target->payment_status);
        $this->assertSame('2026-10-06', $target->payment_at->format('Y-m-d'));
        $this->assertSame(0.0, (float) $target->balance_amount);
    }

    public function test_paying_one_target_does_not_mark_a_same_period_sibling_paid(): void
    {
        $client = $this->client('Bravo');
        $plan = $this->plan('2026-10-05');

        Billing::create([
            'client_id' => $client->id,
            'period_label' => 'TEST BILLING',
            'quarter' => 1,
            'year' => 2026,
            'total' => 2000,
            'status' => Billing::STATUS_UNPAID,
        ]);

        $first = WeeklyBookkeepingTarget::create([
            'weekly_bookkeeping_id' => $plan->id,
            'client_id' => $client->id,
            'task_type' => 'payment',
            'target_date' => '2026-10-05',
            'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
        ]);
        $second = WeeklyBookkeepingTarget::create([
            'weekly_bookkeeping_id' => $plan->id,
            'client_id' => $client->id,
            'task_type' => 'record',
            'target_date' => '2026-10-05',
            'actual_status' => WeeklyBookkeepingTarget::ACTUAL_STATUS_PENDING,
        ]);

        app(PaymentSynchronizer::class)->confirm($first, 2000.0, 'cash', '2026-10-06', $this->admin());

        $this->assertSame(WeeklyBookkeepingTarget::PAYMENT_STATUS_PAID, $first->refresh()->payment_status);
        $this->assertNull($second->refresh()->payment_status);
    }

    public function test_a_partial_payment_leaves_a_balance_and_does_not_mark_paid(): void
    {
        $client = $this->client('Charlie');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);

        $payment = app(PaymentSynchronizer::class)->confirm($target, 400.0, 'gcash', '2026-10-06', $this->admin());

        $target->refresh();

        $this->assertSame(CashPayment::STATUS_PARTIAL, $payment->status);
        $this->assertSame(WeeklyBookkeepingTarget::PAYMENT_STATUS_WITH_BALANCE, $target->payment_status);
        $this->assertSame(600.0, (float) $target->balance_amount);
        $this->assertNotSame(WeeklyBookkeepingTarget::ACTUAL_STATUS_PAID, $target->actual_status);
    }

    public function test_reusing_an_idempotency_key_does_not_double_book(): void
    {
        $client = $this->client('Echo');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);

        $synchronizer = app(PaymentSynchronizer::class);
        $first = $synchronizer->confirm($target, 1000.0, 'cash', '2026-10-06', $this->admin(), 'fixed-key');
        $second = $synchronizer->confirm($target, 1000.0, 'cash', '2026-10-06', $this->admin(), 'fixed-key');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, CashPayment::query()->where('client_id', $client->id)->count());
    }

    public function test_a_non_payment_target_cannot_be_marked_paid(): void
    {
        $client = $this->client('Foxtrot');
        $plan = $this->plan('2026-10-05');
        $target = WeeklyBookkeepingTarget::create([
            'weekly_bookkeeping_id' => $plan->id,
            'client_id' => $client->id,
            'task_type' => 'pickup',
            'target_date' => '2026-10-05',
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(PaymentSynchronizer::class)->confirm($target, 100.0, 'cash', '2026-10-06', $this->admin());
    }

    public function test_the_http_endpoint_rejects_a_target_that_belongs_to_another_client(): void
    {
        $owner = $this->client('Owner Client');
        $other = $this->client('Other Client');
        $target = $this->paymentTarget($owner, '2026-10-05', 1000.0);

        // Attempt to pay the owner's target while claiming a different client.
        $this->actingAs($this->admin())
            ->post(route('admin.cash-daily-position.payments.store'), [
                'client_id' => $other->id,
                'target_type' => 'weekly',
                'target_id' => $target->id,
                'amount' => 1000,
                'method' => 'cash',
                'paid_on' => '2026-10-06',
            ])
            ->assertNotFound();

        $this->assertNull($target->refresh()->payment_status);
    }

    public function test_the_http_endpoint_records_a_payment_against_one_target(): void
    {
        $client = $this->client('Hotel');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);

        $this->actingAs($this->admin())
            ->post(route('admin.cash-daily-position.payments.store'), [
                'client_id' => $client->id,
                'target_type' => 'weekly',
                'target_id' => $target->id,
                'amount' => 1000,
                'method' => 'cash',
                'paid_on' => '2026-10-06',
            ])
            ->assertRedirect();

        $this->assertSame(
            WeeklyBookkeepingTarget::PAYMENT_STATUS_PAID,
            $target->refresh()->payment_status
        );
    }

    // ---- Weekday-only worksheet -----------------------------------------

    public function test_the_monthly_worksheet_has_no_saturday_or_sunday_rows(): void
    {
        $client = $this->client('Weekday Client');

        $response = $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk();

        // October 2026 has 4 Saturdays (3,10,17,24,31 => 5) and 4 Sundays.
        // The header weekday label for a weekend day must never appear as a row.
        $response->assertDontSee('>Sat<', false);
        $response->assertDontSee('>Sun<', false);

        // And a known Monday does appear.
        $response->assertSee('>Mon<', false);
    }

    public function test_a_weekend_payment_is_preserved_and_folded_into_the_nearest_weekday(): void
    {
        $client = $this->client('Weekend Client');
        $target = $this->paymentTarget($client, '2026-10-03', 1000.0);

        // Paid on Saturday 2026-10-03; must not vanish from the report.
        $payment = app(PaymentSynchronizer::class)->confirm($target, 1000.0, 'cash', '2026-10-03', $this->admin());

        // The weekend day's own position carries the cash-received line, so the
        // addition is folded onto the next weekday rather than dropped.
        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-03',
            'beginning_balance' => 0,
            'budget' => 0,
        ]);

        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Weekend client payment',
            'amount' => 1000,
            'source_cash_payment_id' => $payment->id,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('1,000.00')     // the cash is still reported
            ->assertSee('Includes weekend activity folded in');
    }

    public function test_leap_day_appears_as_a_weekday_row_in_a_leap_year(): void
    {
        $client = $this->client('Leap Client');

        // 2024-02-29 is a Thursday.
        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 2,
                'year' => 2024,
            ]))
            ->assertOk()
            ->assertSee('Feb 29');
    }

    // ---- Handwritten design: actual cash + overage / (shortage) ----------

    public function test_overage_is_actual_cash_minus_ending_balance(): void
    {
        $client = $this->client('Overage Client');

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 500,
            'budget' => 1200,
            'actual_cash' => 1300,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'label' => 'Cash received',
            'amount' => 1000,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_EXPENSE,
            'label' => 'Supplies',
            'amount' => 250,
        ]);

        // Ending = 500 + 1000 − 250 = 1250. Actual 1300 → overage 50.
        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('1,250.00')     // ending balance
            ->assertSee('1,300.00')     // actual cash
            ->assertSee('50.00');       // overage
    }

    public function test_a_shortage_is_reported_when_actual_cash_is_below_ending(): void
    {
        $client = $this->client('Shortage Client');

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 1000,
            'budget' => 0,
            'actual_cash' => 950,
        ]);
        CashDailyLine::create([
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_EXPENSE,
            'label' => 'Petty cash',
            'amount' => 0.01,
        ]);

        // Ending = 1000 − 0.01 = 999.99. Actual 950 → shortage 49.99.
        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('999.99')       // ending balance
            ->assertSee('49.99');       // shortage
    }

    public function test_an_uncounted_day_shows_not_counted_not_a_number(): void
    {
        $client = $this->client('Uncounted Client');

        CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 500,
            'budget' => 0,
            'actual_cash' => null,
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Not counted yet');
    }

    // ---- ADD block: reused cash_daily_lines ------------------------------

    public function test_a_client_linked_addition_line_can_be_stored_on_the_day(): void
    {
        $client = $this->client('Addition Client');

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 0,
            'budget' => 0,
        ]);

        $this->actingAs($this->admin())
            ->post(route('admin.cash-daily-position.lines.store'), [
                'cash_daily_position_id' => $position->id,
                'type' => CashDailyLine::TYPE_ADDITION,
                'client_id' => $client->id,
                'label' => 'Walk-in receipt',
                'amount' => 250,
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $line = CashDailyLine::where('type', CashDailyLine::TYPE_ADDITION)->firstOrFail();
        $this->assertSame($client->id, (int) $line->client_id);
        $this->assertSame('250.00', $line->amount);
    }

    public function test_the_same_confirmed_payment_cannot_be_recorded_twice(): void
    {
        $client = $this->client('Dup Payment Client');
        $target = $this->paymentTarget($client, '2026-10-05', 1000.0);
        $payment = app(PaymentSynchronizer::class)->confirm($target, 1000.0, 'cash', '2026-10-05', $this->admin());

        $position = CashDailyPosition::create([
            'client_id' => $client->id,
            'position_date' => '2026-10-05',
            'beginning_balance' => 0,
            'budget' => 0,
        ]);

        $payload = [
            'cash_daily_position_id' => $position->id,
            'type' => CashDailyLine::TYPE_ADDITION,
            'client_id' => $client->id,
            'label' => 'Client payment',
            'amount' => 1000,
            'source_cash_payment_id' => $payment->id,
        ];

        $this->actingAs($this->admin())
            ->post(route('admin.cash-daily-position.lines.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('status');

        // The second attempt is refused with an error, not a duplicate line.
        $this->actingAs($this->admin())
            ->post(route('admin.cash-daily-position.lines.store'), $payload)
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(1, CashDailyLine::where('source_cash_payment_id', $payment->id)->count());
    }

    // ---- Period navigation ----------------------------------------------

    public function test_previous_and_next_periods_roll_across_the_year(): void
    {
        $october = CashReportPeriod::month(2026, 10);
        $this->assertSame('2026-09-01', $october->previous()->startDate());
        $this->assertSame('2026-11-01', $october->next()->startDate());

        $january = CashReportPeriod::month(2026, 1);
        $this->assertSame('2025-12-01', $january->previous()->startDate());

        $q4 = CashReportPeriod::quarter(2026, 4);
        $this->assertSame('2026-07-01', $q4->previous()->startDate());
        $this->assertSame('2027-01-01', $q4->next()->startDate());

        // A first-week period rolls back to the previous month's last week.
        $firstWeek = CashReportPeriod::week(2026, 10, 1);
        $this->assertSame(9, $firstWeek->previous()->month);
    }

    public function test_the_report_renders_previous_and_next_navigation_links(): void
    {
        $client = $this->client('Nav Client');

        $this->actingAs($this->admin())
            ->get(route('admin.cash-daily-position.index', [
                'client_id' => $client->id,
                'period_type' => 'month',
                'month' => 10,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Prev')
            ->assertSee('Next');
    }

    // ---- Compact client search endpoint ----------------------------------

    public function test_the_client_search_endpoint_returns_matching_clients_only(): void
    {
        $this->client('Zamboanga Trading');
        $this->client('Manila Foods');

        $response = $this->actingAs($this->admin())
            ->getJson(route('admin.cash-daily-position.clients.json', ['q' => 'Zamboanga']))
            ->assertOk();

        $names = collect($response->json())->pluck('name');
        $this->assertTrue($names->contains('Zamboanga Trading'));
        $this->assertFalse($names->contains('Manila Foods'));
    }

    public function test_the_client_search_endpoint_requires_authentication(): void
    {
        $this->getJson(route('admin.cash-daily-position.clients.json', ['q' => 'x']))
            ->assertUnauthorized();
    }
}
