<?php

namespace Tests\Feature;

use App\Models\Billing;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use App\Support\Quarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClientBillingCollectionsQuarterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic "now": Wednesday July 15, 2026 → current quarter Q3 2026.
        Carbon::setTestNow(Carbon::parse('2026-07-15 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function client(string $label = 'Quarter Client'): User
    {
        $user = User::create([
            'name' => $label,
            'email' => 'quarter'.uniqid().'@gmail.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);

        ClientSurveyResponse::create([
            'user_id' => $user->id,
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => null,
            'submitted_at' => now(),
        ]);

        return $user;
    }

    private function billing(
        User $client,
        string $status,
        float $total,
        int $quarter,
        int $year = 2026,
        string $paidAt = null,
        string $label = null,
    ): Billing {
        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = $quarter;
        $billing->year = $year;
        $billing->period_label = $label ?: strtoupper(Billing::QUARTERS[$quarter]).' QUARTER '.$year.' BILLING';
        $billing->cash_in = 0;
        $billing->total = $total;
        $billing->status = $status;
        $billing->due_date = now()->addDays(5)->toDateString();
        $billing->paid_at = $paidAt !== null ? Carbon::parse($paidAt) : null;
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        return $billing;
    }

    private function key(int $quarter, int $year = 2026): string
    {
        return sprintf('%d-Q%d', $year, $quarter);
    }

    /* ------------------------------------------------------------------ */
    /* Client billing — 15 regression/feature tests                       */
    /* ------------------------------------------------------------------ */

    public function test_billing_defaults_to_current_quarter_when_current_has_records(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 1000.00, 3, 2026);
        $this->billing($user, Billing::STATUS_PAID, 500.00, 2, 2026, '2026-05-01');

        $response = $this->actingAs($user)->get('/client/billing');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q3');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 1000.0) < 0.001);
    }

    public function test_billing_defaults_to_most_recent_quarter_when_current_is_empty(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($user, Billing::STATUS_PAID, 500.00, 2, 2026, '2026-05-01');

        $response = $this->actingAs($user)->get('/client/billing');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q2');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 500.0) < 0.001);
        $response->assertViewHas('globalUnpaid', fn (float $u) => abs($u - 100.0) < 0.001);
    }

    public function test_billing_quarterly_summary_uses_only_selected_quarter(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($user, Billing::STATUS_PAID, 250.00, 2, 2026, '2026-05-01');
        $this->billing($user, Billing::STATUS_UNPAID, 300.00, 3, 2026);

        $this->actingAs($user)->get('/client/billing?quarter=2026-Q1')
            ->assertOk()
            ->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 100.0) < 0.001
                && abs($s['paid'] - 0.0) < 0.001
                && abs($s['outstanding'] - 100.0) < 0.001);

        $this->actingAs($user)->get('/client/billing?quarter=2026-Q2')
            ->assertOk()
            ->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 250.0) < 0.001
                && abs($s['paid'] - 250.0) < 0.001
                && abs($s['outstanding'] - 0.0) < 0.001);

        $this->actingAs($user)->get('/client/billing?quarter=2026-Q3')
            ->assertOk()
            ->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 300.0) < 0.001
                && abs($s['paid'] - 0.0) < 0.001
                && abs($s['outstanding'] - 300.0) < 0.001);
    }

    public function test_billing_list_is_filtered_by_selected_quarter(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($user, Billing::STATUS_PAID, 250.00, 2, 2026, '2026-05-01');

        $response = $this->actingAs($user)->get('/client/billing?quarter=2026-Q1');

        $response->assertOk();
        $response->assertSee('1ST QUARTER 2026 BILLING');
        $response->assertDontSee('2ND QUARTER 2026 BILLING');
    }

    public function test_billing_quarter_is_url_addressable_without_javascript(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($user, Billing::STATUS_PAID, 250.00, 2, 2026, '2026-05-01');

        $response = $this->actingAs($user)->get('/client/billing?quarter=2026-Q2');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q2');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 250.0) < 0.001);
        $response->assertSee('2ND QUARTER 2026 BILLING');
    }

    public function test_billing_global_unpaid_balance_is_constant_across_quarters(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($user, Billing::STATUS_UNPAID, 60.00, 2, 2026);
        $this->billing($user, Billing::STATUS_PAID, 500.00, 3, 2026, '2026-08-10');

        foreach (['2026-Q1', '2026-Q2', '2026-Q3'] as $quarter) {
            $this->actingAs($user)->get('/client/billing?quarter='.$quarter)
                ->assertOk()
                ->assertViewHas('globalUnpaid', fn (float $u) => abs($u - 160.0) < 0.001)
                ->assertSee('>₱160.00<', false);
        }
    }

    public function test_billing_empty_quarter_shows_zero_summary_and_keeps_global_unpaid(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);

        $response = $this->actingAs($user)->get('/client/billing?quarter=2026-Q3');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q3');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 0.0) < 0.001
            && abs($s['paid'] - 0.0) < 0.001
            && abs($s['outstanding'] - 0.0) < 0.001);
        $response->assertViewHas('globalUnpaid', fn (float $u) => abs($u - 100.0) < 0.001);
        $response->assertSee('No billing statements were recorded for this quarter.');
        $response->assertSee('>₱100.00<', false);
    }

    public function test_billing_invalid_quarter_parameter_falls_back_safely(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 250.00, 2, 2026, '2026-05-01');

        $badCandidates = ['../../', '9999-Q99', '2026-Q0', '2026-Q7', 'hello', '2026-1', '2026', ''];
        foreach ($badCandidates as $candidate) {
            $response = $this->actingAs($user)->get('/client/billing?quarter='.rawurlencode($candidate));

            $response->assertOk();
            $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q2');
        }
    }

    public function test_billing_wellformed_but_unknown_quarter_falls_back_to_default(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 250.00, 2, 2026, '2026-05-01');

        $response = $this->actingAs($user)->get('/client/billing?quarter=2027-Q1');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q2');
        $response->assertDontSee('2027');
    }

    public function test_billing_dropdown_lists_available_quarters_and_current_first(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 250.00, 1, 2026, '2026-02-10');
        $this->billing($user, Billing::STATUS_UNPAID, 300.00, 3, 2026);

        $response = $this->actingAs($user)->get('/client/billing');

        $response->assertOk();
        $response->assertViewHas('availableQuarters', function (array $quarters) {
            return $quarters[0]->key() === '2026-Q3'
                && count($quarters) === 2
                && $quarters[1]->key() === '2026-Q1';
        });
        $response->assertSee('value="2026-Q3"', false);
        $response->assertSee('value="2026-Q3" selected', false);
        $response->assertSee('value="2026-Q1"', false);
    }

    public function test_billing_drafts_are_excluded_from_summary_list_and_unpaid(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($user, Billing::STATUS_DRAFT, 999.00, 2, 2026);

        $response = $this->actingAs($user)->get('/client/billing');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q1');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 100.0) < 0.001);
        $response->assertViewHas('globalUnpaid', fn (float $u) => abs($u - 100.0) < 0.001);
        $response->assertDontSee('999.00');
        $response->assertDontSee('value="2026-Q2"', false);
    }

    public function test_billing_quarter_selection_works_across_years(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 2, 2015);
        $this->billing($user, Billing::STATUS_UNPAID, 300.00, 3, 2026);

        $response = $this->actingAs($user)->get('/client/billing?quarter=2015-Q2');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2015-Q2');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['billed'] - 100.0) < 0.001);
        $response->assertSee('2ND QUARTER 2015 BILLING');
    }

    public function test_billing_security_client_cannot_read_others_through_quarter(): void
    {
        $owner = $this->client('Owner');
        $intruder = $this->client('Intruder');

        $this->billing($owner, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($owner, Billing::STATUS_UNPAID, 50.00, 3, 2026);
        $this->billing($intruder, Billing::STATUS_UNPAID, 777777.00, 1, 2026);

        $response = $this->actingAs($owner)->get('/client/billing?quarter=2026-Q1');

        $response->assertOk();
        $response->assertViewHas('globalUnpaid', fn (float $u) => abs($u - 150.0) < 0.001);
        $response->assertDontSee('777777.00');
    }

    public function test_billing_global_summary_keeps_backcompat_keys(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($user, Billing::STATUS_PAID, 250.00, 2, 2026, '2026-05-01');

        $response = $this->actingAs($user)->get('/client/billing?quarter=2026-Q1');

        $response->assertViewHas('summary', function (array $summary) {
            return abs($summary['billed'] - 350.0) < 0.001
                && abs($summary['paid'] - 250.0) < 0.001
                && abs($summary['outstanding'] - 100.0) < 0.001;
        });
    }

    public function test_billing_no_records_shows_empty_state_and_zero_balance(): void
    {
        $user = $this->client();

        $response = $this->actingAs($user)->get('/client/billing');

        $response->assertOk();
        $response->assertViewHas('globalUnpaid', fn (float $u) => abs($u - 0.0) < 0.001);
        $response->assertSee('No billing statements were recorded for this quarter.');
        $response->assertSee('Your Total Unpaid Balance');
        $response->assertSee('Across all billing periods');
        $response->assertSee('>₱0.00<', false);
    }

    /* ------------------------------------------------------------------ */
    /* Client collections — 12 regression/feature tests                    */
    /* ------------------------------------------------------------------ */

    public function test_collections_defaults_to_current_payment_quarter(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 500.00, 2, 2026, '2026-08-10');

        $response = $this->actingAs($user)->get('/client/collections');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q3');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['paid'] - 500.0) < 0.001
            && $s['count'] === 1);
    }

    public function test_collections_defaults_to_most_recent_payment_quarter(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 500.00, 1, 2026, '2026-02-10');
        $this->billing($user, Billing::STATUS_PAID, 300.00, 2, 2026, '2026-05-05');

        $response = $this->actingAs($user)->get('/client/collections');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q2');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['paid'] - 300.0) < 0.001);
    }

    public function test_collections_filters_by_payment_date_not_bill_period(): void
    {
        $user = $this->client();
        // Issued Q2 2026, but actually PAID in Q3 2026.
        $this->billing($user, Billing::STATUS_PAID, 500.00, 2, 2026, '2026-08-10');
        // Issued Q3 2026, but actually PAID in Q1 2026 (mirror-image mismatch).
        $this->billing($user, Billing::STATUS_PAID, 700.00, 3, 2026, '2026-03-01');

        // Billing page groups each statement by its ISSUED quarter.
        $this->actingAs($user)->get('/client/billing?quarter=2026-Q2')
            ->assertOk()
            ->assertSee('2ND QUARTER 2026 BILLING')
            ->assertDontSee('3RD QUARTER 2026 BILLING');

        // Collections page groups the same statements by the PAID quarter:
        // the Q3-issued payment lands in Q1 collections, the Q2-issued payment
        // in Q3 collections.
        $this->actingAs($user)->get('/client/collections?quarter=2026-Q1')
            ->assertOk()
            ->assertSee('3RD QUARTER 2026 BILLING')
            ->assertDontSee('2ND QUARTER 2026 BILLING')
            ->assertViewHas('quarterSummary', fn (array $s) => abs($s['paid'] - 700.0) < 0.001);

        $this->actingAs($user)->get('/client/collections?quarter=2026-Q3')
            ->assertOk()
            ->assertSee('2ND QUARTER 2026 BILLING')
            ->assertDontSee('3RD QUARTER 2026 BILLING')
            ->assertViewHas('quarterSummary', fn (array $s) => abs($s['paid'] - 500.0) < 0.001);
    }

    public function test_collections_quarter_summary_is_collected_total_for_quarter(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 100.00, 1, 2026, '2026-07-05');
        $this->billing($user, Billing::STATUS_PAID, 150.00, 2, 2026, '2026-08-20');
        $this->billing($user, Billing::STATUS_PAID, 50.00, 3, 2026, '2026-04-10');

        $response = $this->actingAs($user)->get('/client/collections?quarter=2026-Q3');

        $response->assertOk();
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['paid'] - 250.0) < 0.001
            && $s['count'] === 2);
        $response->assertViewHas('summary', function (array $summary) {
            return abs($summary['total'] - 300.0) < 0.001
                && abs($summary['paid'] - 300.0) < 0.001
                && abs($summary['outstanding'] - 0.0) < 0.001;
        });
    }

    public function test_collections_global_unpaid_balance_shown_and_constant(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_UNPAID, 100.00, 1, 2026);
        $this->billing($user, Billing::STATUS_UNPAID, 60.00, 2, 2026);
        $this->billing($user, Billing::STATUS_PAID, 500.00, 3, 2026, '2026-08-10');

        foreach (['2026-Q1', '2026-Q3'] as $quarter) {
            $this->actingAs($user)->get('/client/collections?quarter='.$quarter)
                ->assertOk()
                ->assertViewHas('globalUnpaid', fn (float $u) => abs($u - 160.0) < 0.001)
                ->assertSee('>₱160.00<', false);
        }
    }

    public function test_collections_empty_payment_quarter_zero_and_empty_state(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 500.00, 1, 2026, '2026-02-10');

        $response = $this->actingAs($user)->get('/client/collections?quarter=2026-Q3');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q3');
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['paid'] - 0.0) < 0.001
            && $s['count'] === 0);
        $response->assertSee('No payments were recorded for this quarter.');
    }

    public function test_collections_invalid_quarter_parameter_falls_back_safely(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 500.00, 2, 2026, '2026-05-05');

        foreach (['../../', '9999-Q99', '2026-Q0', 'hello', ''] as $candidate) {
            $this->actingAs($user)->get('/client/collections?quarter='.rawurlencode($candidate))
                ->assertOk()
                ->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q2');
        }
    }

    public function test_collections_wellformed_unknown_quarter_falls_back_to_default(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 500.00, 2, 2026, '2026-05-05');

        $response = $this->actingAs($user)->get('/client/collections?quarter=2027-Q1');

        $response->assertOk();
        $response->assertViewHas('activeQuarter', fn (Quarter $q) => $q->key() === '2026-Q2');
        $response->assertDontSee('2027');
    }

    public function test_collections_dropdown_lists_payment_quarters_with_current_first(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 250.00, 1, 2026, '2026-02-10');
        $this->billing($user, Billing::STATUS_PAID, 300.00, 3, 2026, '2026-08-10');

        $response = $this->actingAs($user)->get('/client/collections');

        $response->assertOk();
        $response->assertViewHas('availableQuarters', function (array $quarters) {
            return $quarters[0]->key() === '2026-Q3'
                && count($quarters) === 2
                && $quarters[1]->key() === '2026-Q1';
        });
        $response->assertSee('value="2026-Q3" selected', false);
        $response->assertSee('value="2026-Q1"', false);
    }

    public function test_collections_unpaid_billings_are_not_listed_as_collections(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 300.00, 1, 2026, '2026-02-10', 'PAID Q1 RETAINER');
        $unpaid = $this->billing($user, Billing::STATUS_UNPAID, 500.00, 2, 2026, null, 'UNPAID Q2 FOLLOW-UP');

        $response = $this->actingAs($user)->get('/client/collections');

        $response->assertOk();
        $response->assertSee('PAID Q1 RETAINER');
        $response->assertDontSee('UNPAID Q2 FOLLOW-UP');
        $this->assertNull($unpaid->paid_at);

        // The current quarter is viewable even when empty.
        $this->actingAs($user)->get('/client/collections?quarter=2026-Q3')
            ->assertOk()
            ->assertSee('No payments were recorded for this quarter.');
    }

    public function test_collections_security_client_cannot_read_others(): void
    {
        $owner = $this->client('Owner');
        $intruder = $this->client('Intruder');

        $this->billing($owner, Billing::STATUS_PAID, 100.00, 3, 2026, '2026-08-10');
        $this->billing($owner, Billing::STATUS_UNPAID, 1234.00, 2, 2026);
        $this->billing($intruder, Billing::STATUS_PAID, 555.00, 3, 2026, '2026-08-12');
        $this->billing($intruder, Billing::STATUS_UNPAID, 77.00, 2, 2026);

        $response = $this->actingAs($owner)->get('/client/collections?quarter=2026-Q3');

        $response->assertOk();
        $response->assertViewHas('quarterSummary', fn (array $s) => abs($s['paid'] - 100.0) < 0.001);
        $response->assertViewHas('globalUnpaid', fn (float $u) => abs($u - 1234.0) < 0.001);
        $response->assertDontSee('555.00');
        $response->assertDontSee('77.00');
    }

    public function test_collections_receipt_link_keeps_from_collections_and_page_renders(): void
    {
        $user = $this->client();
        $this->billing($user, Billing::STATUS_PAID, 500.00, 2, 2026, '2026-08-10');

        $response = $this->actingAs($user)->get('/client/collections?quarter=2026-Q3');

        $response->assertOk();
        $response->assertSee('2ND QUARTER 2026 BILLING');
        $response->assertSee('>₱500.00<', false);
        $response->assertSee('View receipt');
        $response->assertSee('from=collections', false);
    }
}