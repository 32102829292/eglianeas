<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\BirFormStatus;
use App\Models\BirFormType;
use App\Models\ClientCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientListBirFilterTest extends TestCase
{
    use RefreshDatabase;

    private function internal(string $role, string $label): User
    {
        return User::create([
            'name' => $label,
            'email' => strtolower($role).uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => $role,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function admin(): User
    {
        return $this->internal(User::ROLE_ADMIN, 'BirFilter Admin');
    }

    private function client(string $businessName): User
    {
        $client = $this->internal(User::ROLE_CLIENT, 'Owner of '.$businessName);
        $client->update(['business_name' => $businessName]);

        ClientCompany::create([
            'client_id' => $client->id,
            'branch_number' => 1,
            'company_code' => $client->client_code.'-01',
            'company_name' => $businessName.' Co',
        ]);

        return $client;
    }

    private function assign(User $client, string $formType, bool $applicable = true, string $status = BirFormStatus::STATUS_NOT_FILED): BirFormStatus
    {
        $companyId = $client->companies()->where('branch_number', 1)->value('id');

        return BirFormStatus::create([
            'client_id' => $client->id,
            'client_company_id' => $companyId,
            'form_type' => $formType,
            'status' => $status,
            'applicable' => $applicable,
        ]);
    }

    public function test_client_list_renders_without_any_filter(): void
    {
        $admin = $this->admin();
        $client = $this->client('Baseline Trading');
        $this->assign($client, '1701');

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertSee('All Clients');
        $response->assertSee('Baseline Trading');
        $response->assertSee('1701');
    }

    public function test_filter_matches_clients_with_any_selected_code(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $beta = $this->client('Beta Corp');
        $gamma = $this->client('Gamma Corp');
        $this->assign($alpha, '1701');
        $this->assign($beta, '2551Q');
        $this->assign($gamma, '0619E');

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => ['1701', '2551Q']]));

        $response->assertOk();
        $response->assertSee('Alpha Corp');
        $response->assertSee('Beta Corp');
        $response->assertDontSee('Gamma Corp');
        $response->assertSee('Matching Clients');
    }

    public function test_filter_accepts_a_comma_separated_list(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $beta = $this->client('Beta Corp');
        $this->assign($alpha, '1701');
        $this->assign($beta, '0619E');

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => '1701,0619E']));

        $response->assertOk();
        $response->assertSee('Alpha Corp');
        $response->assertSee('Beta Corp');
    }

    public function test_filter_is_case_insensitive_and_whitespace_tolerant(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $this->assign($alpha, '1701');

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => [' 1701 ']]));

        $response->assertOk();
        $response->assertSee('Alpha Corp');
    }

    public function test_clients_without_the_selected_code_are_excluded(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $beta = $this->client('Beta Corp');
        $this->assign($alpha, '1701');
        $this->assign($beta, '1701Q');

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => ['1701']]));

        $response->assertOk();
        $response->assertSee('Alpha Corp');
        $response->assertDontSee('Beta Corp');
    }

    public function test_codes_that_are_not_applicable_are_ignored_by_the_filter(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $this->assign($alpha, '1701', applicable: false);

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => ['1701']]));

        $response->assertOk();
        $response->assertDontSee('Alpha Corp');
        $response->assertSee('No clients match the current filters.');
    }

    public function test_unknown_codes_are_ignored_and_do_not_break_the_query(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $beta = $this->client('Beta Corp');
        $this->assign($alpha, '1701');
        $this->assign($beta, '2551Q');

        // 0605 is not in the seeded master list; it must be dropped rather than
        // injected into the query, and 1701 should still filter normally.
        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => ['0605', '1701']]));

        $response->assertOk();
        $response->assertSee('Alpha Corp');
        $response->assertDontSee('Beta Corp');
    }

    public function test_filter_composes_with_the_existing_search(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $beta = $this->client('Beta Corp');
        $gamma = $this->client('Gamma Corp');
        $this->assign($alpha, '1701');
        $this->assign($beta, '1701');
        $this->assign($gamma, '1701');

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['q' => 'Alpha', 'bir_codes' => ['1701']]));

        $response->assertOk();
        $response->assertSee('Alpha Corp');
        $response->assertDontSee('Beta Corp');
        $response->assertDontSee('Gamma Corp');
    }

    public function test_selected_codes_are_rendered_as_checked_filter_chips(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $this->assign($alpha, '1701');

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => ['1701']]));

        $response->assertOk();
        $response->assertSee('name="bir_codes[]"', false);
        $response->assertSee('value="1701" checked', false);
        $response->assertSee('1 selected');
    }

    public function test_active_filter_chips_and_clear_link_appear_when_filtered(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $this->assign($alpha, '1701');

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => ['1701']]));

        $response->assertOk();
        $response->assertSee('Active filters:');
        $response->assertSee('BIR: 1701');
        $response->assertSee('Clear filters');
    }

    public function test_no_chips_or_clear_link_without_filters(): void
    {
        $admin = $this->admin();
        $this->client('Alpha Corp');

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertDontSee('Active filters:');
        $response->assertSee('All codes');
    }

    public function test_only_applicable_codes_are_shown_in_the_codes_column(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $this->assign($alpha, '1701', applicable: true, status: BirFormStatus::STATUS_FILED);
        $this->assign($alpha, '2551Q', applicable: false);

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertSee('bir-chip bir-chip-filed" title="Filed">1701', false);
        // 2551Q is flagged but not applicable, so it must not render as a row
        // chip. It is still offered in the filter list, which is expected.
        $response->assertDontSee('title="Not filed">2551Q', false);
    }

    public function test_a_client_with_no_codes_shows_a_dash(): void
    {
        $admin = $this->admin();
        $this->client('Alpha Corp');

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertSee('data-col="BIR Codes"', false);
    }

    public function test_codes_are_deduplicated_across_a_clients_branches(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $this->assign($alpha, '1701', applicable: true, status: BirFormStatus::STATUS_NOT_FILED);

        // A second branch flags the same code as filed. The chip must appear
        // once, carrying the most advanced status.
        $second = ClientCompany::create([
            'client_id' => $alpha->id,
            'branch_number' => 2,
            'company_code' => $alpha->client_code.'-02',
            'company_name' => 'Alpha Corp Branch 2',
        ]);
        BirFormStatus::create([
            'client_id' => $alpha->id,
            'client_company_id' => $second->id,
            'form_type' => '1701',
            'status' => BirFormStatus::STATUS_FILED,
            'applicable' => true,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertSee('bir-chip bir-chip-filed" title="Filed">1701', false);
    }

    public function test_master_list_drives_the_filter_options(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        foreach (BirFormStatus::getFormTypeCodes() as $code) {
            $response->assertSee('value="'.$code.'"', false);
        }
    }

    public function test_an_administrator_added_code_appears_as_an_option(): void
    {
        $admin = $this->admin();
        BirFormType::create(['code' => '0605', 'name' => 'BIR Form 0605', 'active' => true, 'sort_order' => 99]);

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertSee('value="0605"', false);
    }

    public function test_inactive_master_codes_are_not_offered(): void
    {
        $admin = $this->admin();
        BirFormType::create(['code' => '9999', 'name' => 'Retired Form', 'active' => false, 'sort_order' => 100]);

        $response = $this->actingAs($admin)->get(route('admin.clients.index'));

        $response->assertOk();
        $response->assertDontSee('value="9999"', false);
    }

    public function test_sort_controls_preserve_the_bir_filter(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $this->assign($alpha, '1701');

        $response = $this->actingAs($admin)->get(route('admin.clients.index', ['bir_codes' => ['1701']]));

        $response->assertOk();
        // The sort form carries the active codes so re-sorting does not drop them.
        $response->assertSee('name="bir_codes[]" value="1701"', false);
    }

    public function test_pagination_preserves_the_bir_filter(): void
    {
        $admin = $this->admin();

        foreach (range(1, 55) as $index) {
            $client = $this->client("Paginated {$index}");
            $this->assign($client, '1701');
        }

        $response = $this->actingAs($admin)->get(route('admin.clients.index', ['bir_codes' => ['1701']]));

        $response->assertOk();
        // Laravel re-encodes the array param with an explicit index when it
        // rebuilds paginator links, so accept either spelling.
        $this->assertMatchesRegularExpression(
            '/bir_codes(?:%5B0%5D|\[\])=1701/',
            $response->getContent()
        );
    }

    public function test_filtered_count_is_reported(): void
    {
        $admin = $this->admin();
        $alpha = $this->client('Alpha Corp');
        $beta = $this->client('Beta Corp');
        $this->assign($alpha, '1701');
        $this->assign($beta, '0619E');

        $response = $this->actingAs($admin)
            ->get(route('admin.clients.index', ['bir_codes' => ['1701']]));

        $response->assertOk();
        $response->assertSee('Matching Clients');
        $this->assertStringContainsString('<span class="count-pill">1</span>', $response->getContent());
    }

    public function test_staff_and_supervisor_may_filter_the_list(): void
    {
        $staff = $this->internal(User::ROLE_STAFF, 'BirFilter Staff');
        $supervisor = $this->internal(User::ROLE_SUPERVISOR, 'BirFilter Supervisor');
        $alpha = $this->client('Alpha Corp');
        $beta = $this->client('Beta Corp');
        $this->assign($alpha, '1701');
        $this->assign($beta, '0619E');

        $this->actingAs($staff)->get(route('admin.clients.index', ['bir_codes' => ['1701']]))
            ->assertOk()
            ->assertSee('Alpha Corp')
            ->assertDontSee('Beta Corp');

        $this->actingAs($supervisor)->get(route('admin.clients.index', ['bir_codes' => ['1701']]))
            ->assertOk()
            ->assertSee('Alpha Corp')
            ->assertDontSee('Beta Corp');
    }

    public function test_clients_cannot_reach_the_admin_client_list(): void
    {
        $client = $this->client('Alpha Corp');

        $this->actingAs($client)->get(route('admin.clients.index'))->assertForbidden();
    }
}