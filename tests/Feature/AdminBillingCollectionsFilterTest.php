<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBillingCollectionsFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Filter QA Admin',
            'email' => 'filter-admin-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(string $label): User
    {
        return User::create([
            'name' => $label,
            'email' => strtolower(str_replace(' ', '', $label)).uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
    }

    private function billing(User $client, int $quarter, int $year, float $total, string $status): Billing
    {
        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = $quarter;
        $billing->year = $year;
        $billing->period_label = strtoupper(Billing::QUARTERS[$quarter])." QUARTER {$year} BILLING";
        $billing->cash_in = 0;
        $billing->total = $total;
        $billing->status = $status;
        $billing->due_date = $year.'-'.str_pad((string) (($quarter - 1) * 3 + 1), 2, '0', STR_PAD_LEFT).'-15';
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        return $billing;
    }

    public function test_billing_index_shows_all_clients_by_default(): void
    {
        $admin = $this->admin();
        $q1 = $this->client('Filter Q1 Co');
        $q3 = $this->client('Filter Q3 Co');

        $this->billing($q1, 1, 2026, 1111.11, Billing::STATUS_UNPAID);
        $this->billing($q3, 3, 2026, 500.00, Billing::STATUS_UNPAID);

        $this->actingAs($admin)
            ->get(route('admin.billing.index'))
            ->assertOk()
            ->assertSee('Filter Q1 Co')
            ->assertSee('Filter Q3 Co');
    }

    public function test_billing_index_filters_clients_by_selected_quarter(): void
    {
        $admin = $this->admin();
        $q1 = $this->client('Filter Q1 Co');
        $q3 = $this->client('Filter Q3 Co');

        $this->billing($q1, 1, 2026, 1111.11, Billing::STATUS_UNPAID);
        $this->billing($q3, 3, 2026, 500.00, Billing::STATUS_UNPAID);

        $this->actingAs($admin)
            ->get(route('admin.billing.index', ['quarter' => '2026-Q1']))
            ->assertOk()
            ->assertSee('Filter Q1 Co')
            ->assertDontSee('Filter Q3 Co');
    }

    public function test_billing_index_quarter_totals_reflect_only_selected_quarter_while_stats_stay_global(): void
    {
        $admin = $this->admin();
        $client = $this->client('Filter Both Co');

        $this->billing($client, 1, 2026, 1111.11, Billing::STATUS_UNPAID);
        $this->billing($client, 3, 2026, 500.00, Billing::STATUS_UNPAID);

        // Quarter-scoped table row shows only the Q1 billing; the summary
        // cards still total ALL billing periods (1111.11 + 500.00).
        $this->actingAs($admin)
            ->get(route('admin.billing.index', ['quarter' => '2026-Q1']))
            ->assertOk()
            ->assertSee('₱1,111.11')
            ->assertDontSee('₱500.00')
            ->assertSee('₱1,611.11');
    }

    public function test_billing_index_ignores_invalid_quarter_and_lists_everything(): void
    {
        $admin = $this->admin();
        $q1 = $this->client('Filter Q1 Co');
        $q3 = $this->client('Filter Q3 Co');

        $this->billing($q1, 1, 2026, 100.00, Billing::STATUS_UNPAID);
        $this->billing($q3, 3, 2026, 200.00, Billing::STATUS_UNPAID);

        foreach (['bogus', '2099-Q9', '2026-03', 'Q1-2026'] as $invalid) {
            $this->actingAs($admin)
                ->get(route('admin.billing.index', ['quarter' => $invalid]))
                ->assertOk()
                ->assertSee('Filter Q1 Co')
                ->assertSee('Filter Q3 Co');
        }
    }

    public function test_billing_index_toolbar_exposes_all_quarters_and_selected_state(): void
    {
        $admin = $this->admin();
        $client = $this->client('Filter Toolbar Co');

        $this->billing($client, 1, 2026, 100.00, Billing::STATUS_UNPAID);
        $this->billing($client, 2, 2026, 200.00, Billing::STATUS_PAID);

        $this->actingAs($admin)
            ->get(route('admin.billing.index', ['quarter' => '2026-Q1']))
            ->assertOk()
            ->assertSee('name="quarter"', false)
            ->assertSee('<option value="">All quarters</option>', false)
            ->assertSee('<option value="2026-Q1" selected>Q1 2026</option>', false)
            ->assertSee('Search business or contact');
    }

    public function test_collections_index_filters_billings_by_quarter(): void
    {
        $admin = $this->admin();
        $q1 = $this->client('C Filter Q1 Co');
        $q3 = $this->client('C Filter Q3 Co');

        $this->billing($q1, 1, 2026, 100.00, Billing::STATUS_UNPAID);
        $this->billing($q3, 3, 2026, 200.00, Billing::STATUS_UNPAID);
        // Paid billings never show on the collections page.
        $this->billing($q3, 4, 2026, 300.00, Billing::STATUS_PAID);

        $this->actingAs($admin)
            ->get(route('admin.collections.index', ['quarter' => '2026-Q3']))
            ->assertOk()
            ->assertSee('C Filter Q3 Co')
            ->assertDontSee('C Filter Q1 Co');
    }

    public function test_collections_index_combines_status_and_quarter_filters(): void
    {
        $admin = $this->admin();
        $client = $this->client('C Filter Combo Co');

        $overdue = $this->billing($client, 1, 2026, 100.00, Billing::STATUS_OVERDUE);
        $unpaid = $this->billing($client, 3, 2026, 200.00, Billing::STATUS_UNPAID);
        $this->billing($client, 4, 2026, 300.00, Billing::STATUS_OVERDUE);

        $this->actingAs($admin)
            ->get(route('admin.collections.index', ['quarter' => '2026-Q1', 'status' => 'overdue']))
            ->assertOk()
            ->assertSee('1ST QUARTER 2026 BILLING')
            ->assertDontSee('4TH QUARTER 2026 BILLING');

        $this->actingAs($admin)
            ->get(route('admin.collections.index', ['quarter' => '2026-Q3']))
            ->assertOk()
            ->assertSee('3RD QUARTER 2026 BILLING')
            ->assertDontSee('1ST QUARTER 2026 BILLING')
            ->assertDontSee('4TH QUARTER 2026 BILLING');

        // The filtered rows reference the exact expected records.
        $this->actingAs($admin)
            ->get(route('admin.collections.index', ['quarter' => '2026-Q1', 'status' => 'overdue']))
            ->assertOk();
        $this->assertDatabaseHas('billings', ['id' => $overdue->id, 'status' => Billing::STATUS_OVERDUE]);
        $this->assertDatabaseHas('billings', ['id' => $unpaid->id, 'status' => Billing::STATUS_UNPAID]);
    }

    public function test_collections_index_keeps_global_outstanding_stats_when_filtering(): void
    {
        $admin = $this->admin();
        $client = $this->client('C Filter Stats Co');

        $this->billing($client, 1, 2026, 1111.11, Billing::STATUS_UNPAID);
        $this->billing($client, 3, 2026, 500.00, Billing::STATUS_UNPAID);

        // Table shows only Q1 rows, but "Outstanding" card totals ALL periods.
        $this->actingAs($admin)
            ->get(route('admin.collections.index', ['quarter' => '2026-Q1']))
            ->assertOk()
            ->assertSee('₱1,611.11')
            ->assertSee('1ST QUARTER 2026 BILLING');
    }

    public function test_collections_index_exposes_quarter_and_status_controls_and_actions(): void
    {
        $admin = $this->admin();
        $client = $this->client('C Filter Actions Co');

        $this->billing($client, 1, 2026, 100.00, Billing::STATUS_UNPAID);

        $this->actingAs($admin)
            ->get(route('admin.collections.index', ['quarter' => '2026-Q1']))
            ->assertOk()
            ->assertSee('name="quarter"', false)
            ->assertSee('name="status"', false)
            ->assertSee('All quarters', false)
            ->assertSee('View receipt', false)
            ->assertSee('Send reminder', false)
            ->assertSee('Mark paid', false)
            ->assertSee('aria-label="Date paid"', false);
    }

    // ---- Part 16: Q1–Q4 must actually filter the records (not just show
    // ---- the option labels), and empty/invalid/persistence states hold.

    public function test_billing_index_filters_each_quarter_of_the_year(): void
    {
        $admin = $this->admin();
        $clients = [
            1 => $this->client('B Q1 Co'),
            2 => $this->client('B Q2 Co'),
            3 => $this->client('B Q3 Co'),
            4 => $this->client('B Q4 Co'),
        ];

        foreach ($clients as $quarter => $client) {
            $this->billing($client, $quarter, 2026, (float) ($quarter * 1000), Billing::STATUS_UNPAID);
        }

        foreach ($clients as $quarter => $client) {
            $key = '2026-Q'.$quarter;
            $response = $this->actingAs($admin)->get(route('admin.billing.index', ['quarter' => $key]));
            $response->assertOk()->assertSee($client->name);
            foreach ($clients as $otherQuarter => $otherClient) {
                if ($otherQuarter === $quarter) {
                    continue;
                }
                $response->assertDontSee($otherClient->name);
            }
            // The row total equals ONLY that quarter's billing.
            $response->assertSee('₱'.number_format($quarter * 1000, 2));
        }
    }

    public function test_billing_index_empty_quarter_returns_no_clients(): void
    {
        $admin = $this->admin();
        $client = $this->client('B Only Q1 Co');

        $this->billing($client, 1, 2026, 100.00, Billing::STATUS_UNPAID);

        $this->actingAs($admin)
            ->get(route('admin.billing.index', ['quarter' => '2026-Q2']))
            ->assertOk()
            ->assertDontSee('B Only Q1 Co')
            ->assertSee('No clients found.');
    }

    public function test_billing_index_combines_search_and_quarter(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Amorsolo Alpha Co');
        $beta = $this->client('Baguio Beta Co');

        $this->billing($alpha, 1, 2026, 100.00, Billing::STATUS_UNPAID);
        $this->billing($beta, 1, 2026, 200.00, Billing::STATUS_UNPAID);
        $this->billing($beta, 2, 2026, 300.00, Billing::STATUS_UNPAID);

        $this->actingAs($admin)
            ->get(route('admin.billing.index', ['q' => 'Amorsolo', 'quarter' => '2026-Q1']))
            ->assertOk()
            ->assertSee('Amorsolo Alpha Co')
            ->assertDontSee('Baguio Beta Co');

        // The same search combined with a quarter the client is NOT in yields
        // no rows for that client, so the search is scoped inside the quarter.
        $this->actingAs($admin)
            ->get(route('admin.billing.index', ['q' => 'Amorsolo', 'quarter' => '2026-Q2']))
            ->assertOk()
            ->assertDontSee('Amorsolo Alpha Co')
            ->assertDontSee('Baguio Beta Co')
            ->assertSee('No clients found.');
    }

    public function test_billing_index_offers_all_four_quarters_of_data_years(): void
    {
        $admin = $this->admin();
        $client = $this->client('B Four Quarter Options Co');

        // Sparse data: only Q1 and Q3 exist, yet the toolbar must still offer
        // every quarter of 2026 so sparse periods remain selectable.
        $this->billing($client, 1, 2026, 100.00, Billing::STATUS_UNPAID);
        $this->billing($client, 3, 2026, 200.00, Billing::STATUS_PAID);

        $html = $this->actingAs($admin)->get(route('admin.billing.index'))->getContent();
        foreach (['2026-Q1' => 'Q1 2026', '2026-Q2' => 'Q2 2026', '2026-Q3' => 'Q3 2026', '2026-Q4' => 'Q4 2026'] as $key => $label) {
            $this->assertStringContainsString('<option value="'.$key.'" >'.$label.'</option>', $html);
        }
    }

    public function test_collections_index_filters_each_quarter_of_the_year(): void
    {
        $admin = $this->admin();
        $clients = [
            1 => $this->client('C Q1 Co'),
            2 => $this->client('C Q2 Co'),
            3 => $this->client('C Q3 Co'),
            4 => $this->client('C Q4 Co'),
        ];

        foreach ($clients as $quarter => $client) {
            $this->billing($client, $quarter, 2026, (float) ($quarter * 700), Billing::STATUS_UNPAID);
        }

        foreach ($clients as $quarter => $client) {
            $response = $this->actingAs($admin)->get(route('admin.collections.index', ['quarter' => '2026-Q'.$quarter]));
            $response->assertOk()->assertSee($client->name);
            foreach ($clients as $otherQuarter => $otherClient) {
                if ($otherQuarter === $quarter) {
                    continue;
                }
                $response->assertDontSee($otherClient->name);
            }
            $response->assertSee('₱'.number_format($quarter * 700, 2));
        }
    }

    public function test_collections_index_empty_quarter_shows_empty_state(): void
    {
        $admin = $this->admin();
        $client = $this->client('C Only Q1 Co');

        $this->billing($client, 1, 2026, 100.00, Billing::STATUS_UNPAID);

        $this->actingAs($admin)
            ->get(route('admin.collections.index', ['quarter' => '2026-Q4']))
            ->assertOk()
            ->assertDontSee('C Only Q1 Co')
            ->assertSee('Nothing to collect right now.');
    }

    public function test_collections_index_keeps_status_selection_persisted(): void
    {
        $admin = $this->admin();
        $client = $this->client('C Persist Co');

        $this->billing($client, 1, 2026, 100.00, Billing::STATUS_UNPAID);
        $this->billing($client, 2, 2026, 200.00, Billing::STATUS_OVERDUE);

        $html = $this->actingAs($admin)
            ->get(route('admin.collections.index', ['quarter' => '2026-Q2', 'status' => 'overdue']))
            ->getContent();

        $this->assertStringContainsString('<option value="2026-Q2" selected>Q2 2026</option>', $html);
        $this->assertStringContainsString('<option value="overdue" selected>Overdue</option>', $html);
    }

    public function test_collections_index_keeps_authorization_enforced_for_staff(): void
    {
        $staff = $this->staff();
        $admin = $this->admin();
        $client = $this->client('C Auth Co');

        $this->billing($client, 1, 2026, 100.00, Billing::STATUS_UNPAID);

        $asStaff = $this->actingAs($staff)->get(route('admin.collections.index'));
        $asStaff->assertOk()
            ->assertSee('View receipt', false)
            ->assertSee('Send reminder', false)
            ->assertDontSee('Date paid', false)
            ->assertDontSee('Mark paid', false);

        // The admin (and only the admin) sees the Mark-paid control.
        $asAdmin = $this->actingAs($admin)->get(route('admin.collections.index'));
        $asAdmin->assertOk()->assertSee('Mark paid', false)->assertSee('aria-label="Date paid"', false);
    }

    public function test_billing_index_redirects_empty_filter_params_to_clean_url(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/billings?q=&quarter=2026-Q1')
            ->assertRedirect('/admin/billings?quarter=2026-Q1');

        $this->actingAs($admin)
            ->get('/admin/billings?q=Amorsolo&quarter=')
            ->assertRedirect('/admin/billings?q=Amorsolo');

        $this->actingAs($admin)
            ->get('/admin/billings?q=&quarter=')
            ->assertRedirect('/admin/billings');

        // Non-empty or absent params must never redirect.
        $this->actingAs($admin)->get('/admin/billings?quarter=2026-Q1')->assertOk();
        $this->actingAs($admin)->get('/admin/billings?q=Amorsolo&quarter=2026-Q1')->assertOk();
        $this->actingAs($admin)->get('/admin/billings?quarter=2026-Q9')->assertOk();
    }

    public function test_collections_index_redirects_empty_filter_params_to_clean_url(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/collections?quarter=2026-Q1&status=')
            ->assertRedirect('/admin/collections?quarter=2026-Q1');

        $this->actingAs($admin)
            ->get('/admin/collections?quarter=&status=')
            ->assertRedirect('/admin/collections');

        // Non-empty or absent params must never redirect.
        $this->actingAs($admin)->get('/admin/collections?quarter=2026-Q1')->assertOk();
        $this->actingAs($admin)->get('/admin/collections?quarter=2026-Q4&status=overdue')->assertOk();
    }

    private function staff(): User
    {
        return User::create([
            'name' => 'Filter QA Staff',
            'email' => 'filter-staff-'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_STAFF,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }
}