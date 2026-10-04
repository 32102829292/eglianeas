<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ClientProfile;
use App\Models\ClientSurveyResponse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SupervisorAccessTest extends TestCase
{
    use RefreshDatabase;

    private function supervisor(bool $acknowledged = true): User
    {
        return User::create([
            'name' => 'Supervisor User',
            'email' => 'supervisor'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_SUPERVISOR,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => $acknowledged ? now() : null,
            'confidentiality_ack_version' => $acknowledged ? EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION : null,
        ]);
    }

    private function admin(): User
    {
        return User::create([
            'name' => 'System Admin',
            'email' => 'sys.admin'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(): User
    {
        $user = User::create([
            'name' => 'Supervisor Test Client',
            'email' => 'sclient'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
            'approved_at' => now(),
            'business_name' => 'Supervisor Test Business',
        ]);

        ClientProfile::create([
            'user_id' => $user->id,
            'status' => ClientProfile::STATUS_CURRENT,
            'payment_status' => 'paid',
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

    private function sign(User $supervisor): void
    {
        $this->actingAs($supervisor)
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            ]);
    }

    public function test_supervisor_can_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->supervisor())
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_fresh_supervisor_must_acknowledge_confidentiality_v2_first(): void
    {
        Storage::fake('supabase');

        $supervisor = $this->supervisor(false);

        $this->actingAs($supervisor)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('terms'));

        $this->sign($supervisor);

        $this->actingAs($supervisor)
            ->get(route('admin.dashboard'))
            ->assertOk();
    }

    public function test_supervisor_can_read_client_data(): void
    {
        $client = $this->client();
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->assertSee('Supervisor Test Business');

        $this->actingAs($supervisor)
            ->get(route('admin.clients.show', $client))
            ->assertOk();
    }

    public function test_supervisor_cannot_write_to_clients(): void
    {
        $supervisor = $this->supervisor();
        $client = $this->client();
        $entry = \App\Models\ClientInfoEntry::create(['user_id' => $client->id, 'key' => 'Fiscal Year End', 'value' => 'December 31']);

        $this->actingAs($supervisor)->get(route('admin.clients.create'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.clients.edit', $client))->assertForbidden();
        $this->actingAs($supervisor)->put(route('admin.clients.update', $client), ['name' => 'X', 'email' => 'x@example.com', 'status' => 'current'])->assertForbidden();
        $this->actingAs($supervisor)->delete(route('admin.clients.destroy', $client))->assertForbidden();
        $this->actingAs($supervisor)->post(route('admin.clients.impersonate', $client))->assertForbidden();
        $this->actingAs($supervisor)->post(route('admin.clients.storeInfoEntry', $client), ['key' => 'K', 'value' => 'V'])->assertForbidden();
        $this->actingAs($supervisor)->put(route('admin.clients.updateInfoEntry', [$client, $entry]), ['key' => 'K', 'value' => 'V'])->assertForbidden();
        $this->actingAs($supervisor)->delete(route('admin.clients.destroyInfoEntry', [$client, $entry]))->assertForbidden();
    }

    public function test_supervisor_can_manage_team_accounts(): void
    {
        Mail::fake();

        $supervisor = $this->supervisor();

        $email = 'created.staff'.uniqid().'@example.com';

        $this->actingAs($supervisor)->get(route('admin.users.index'))->assertOk();

        $this->actingAs($supervisor)->post(route('admin.users.store'), [
            'name' => 'Created Staff',
            'email' => $email,
            'role' => User::ROLE_STAFF,
        ])->assertRedirect(route('admin.users.index'));

        $created = User::where('email', $email)->firstOrFail();

        $this->actingAs($supervisor)->get(route('admin.users.edit', $created))->assertOk();

        $this->actingAs($supervisor)->put(route('admin.users.update', $created), [
            'name' => 'Renamed Staff',
            'email' => $email,
            'role' => User::ROLE_STAFF,
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('Renamed Staff', $created->refresh()->name);

        $this->actingAs($supervisor)->delete(route('admin.users.destroy', $created))
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', ['id' => $created->id]);
    }

    public function test_supervisor_cannot_delete_own_account_via_team_accounts(): void
    {
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->delete(route('admin.users.destroy', $supervisor))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $supervisor->id]);
    }

    public function test_supervisor_cannot_access_admin_only_areas(): void
    {
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->get(route('admin.activity-logs'))->assertForbidden();
        $this->actingAs($supervisor)->get('/admin/confidentiality/signatures')->assertNotFound();
        $this->actingAs($supervisor)->get(route('admin.clients.pending'))->assertForbidden();
    }

    public function test_supervisor_can_read_billing_and_service_pages(): void
    {
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->get(route('admin.billing.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('admin.collections.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('admin.other-services.billing'))->assertOk();
        $this->actingAs($supervisor)->get(route('admin.service-tracker.index'))->assertOk();
        $this->actingAs($supervisor)->get(route('admin.announcements.index'))->assertOk();
    }

    public function test_supervisor_nav_shows_team_accounts_and_hides_admin_only_links(): void
    {
        $this->actingAs($this->supervisor())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Client List')
            ->assertSee('Billing Statements')
            ->assertSee('Service Tracker')
            ->assertSee('Team Accounts')
            ->assertDontSee('Pending Accounts')
            ->assertDontSee('Confidentiality Signatures')
            ->assertDontSee('Activity Logs');
    }

    public function test_supervisor_can_acknowledge_and_logs_a_signature(): void
    {
        Storage::fake('supabase');

        $supervisor = $this->supervisor(false);

        $this->sign($supervisor);

        $supervisor->refresh();

        $this->assertSame(EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION, $supervisor->confidentiality_ack_version);
        $this->assertNotNull($supervisor->confidentiality_acknowledged_at);
        $this->assertDatabaseHas('signatures', [
            'user_id' => $supervisor->id,
            'policy_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    public function test_supervisor_dashboard_route_is_admin_dashboard(): void
    {
        $supervisor = $this->supervisor();

        $this->assertSame(route('admin.dashboard'), $supervisor->getDashboardRoute());
    }
}