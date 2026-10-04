<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ClientProfile;
use App\Models\ClientSurveyResponse;
use App\Models\Signature;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ClientApprovalTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'Approval Admin');
    }

    private function staff(): User
    {
        return $this->internal(User::ROLE_STAFF, 'Approval Staff');
    }

    private function client(array $overrides = []): User
    {
        return User::create(array_merge([
            'name' => 'Approval Client',
            'email' => 'approval'.uniqid().'@gmail.com',
            'password' => bcrypt('secret123'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
            'business_name' => 'Approval Client Business',
        ], $overrides));
    }

    private function approve(User $client): void
    {
        $client->update(['approved_at' => now()]);
    }

    private function addSurvey(User $client): void
    {
        ClientSurveyResponse::create([
            'user_id' => $client->id,
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => null,
            'submitted_at' => now(),
        ]);
    }

    private function addAck(User $client): void
    {
        $client->update([
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    public function test_pending_client_is_blocked_from_portal_pages(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertRedirect(route('client.pending-approval'));

        $this->actingAs($client)
            ->get(route('client.billing.index'))
            ->assertRedirect(route('client.pending-approval'));

        $this->actingAs($client)
            ->get(route('client.survey.show'))
            ->assertRedirect(route('client.pending-approval'));
    }

    public function test_pending_client_cannot_access_dashboard(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertRedirect(route('client.pending-approval'));
    }

    public function test_rejected_client_cannot_access_dashboard(): void
    {
        $client = $this->client(['declined_at' => now(), 'decline_reason' => 'Requirements incomplete.']);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertRedirect(route('client.pending-approval'));
    }

    public function test_approved_client_can_access_dashboard(): void
    {
        $client = $this->client();
        $this->approve($client);
        $this->addSurvey($client);
        $this->addAck($client);

        $this->actingAs($client)
            ->get(route('dashboard'))
            ->assertRedirect(route('client.dashboard'));

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk();
    }

    public function test_pending_client_status_page_is_accessible(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('client.pending-approval'))
            ->assertOk()
            ->assertSee('Account Pending Approval');
    }

    public function test_rejected_client_status_page_is_accessible(): void
    {
        $client = $this->client(['declined_at' => now(), 'decline_reason' => 'Denied after review.']);

        $this->actingAs($client)
            ->get(route('client.pending-approval'))
            ->assertOk()
            ->assertSee('Account Not Approved')
            ->assertSee('Denied after review.');
    }

    public function test_pending_client_sees_account_status_page(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('client.pending-approval'))
            ->assertOk()
            ->assertSee('Account Pending Approval');
    }

    public function test_pending_client_can_still_manage_security_and_notifications(): void
    {
        $client = $this->client();

        $this->actingAs($client)
            ->get(route('security.index'))
            ->assertOk();

        $this->actingAs($client)
            ->get(route('notifications.index'))
            ->assertOk();
    }

    public function test_approved_client_with_survey_reaches_portal_dashboard(): void
    {
        $client = $this->client();
        $this->approve($client);
        $this->addSurvey($client);
        $this->addAck($client);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk();
    }

    public function test_approved_surveyed_client_without_ack_is_sent_to_ack_page(): void
    {
        $client = $this->client();
        $this->approve($client);
        $this->addSurvey($client);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertRedirect(route('terms'));

        $this->actingAs($client)
            ->get(route('terms'))
            ->assertOk()
            ->assertSee('Client Signature')
            ->assertSee('Data Privacy Acknowledgement');
    }

    public function test_client_acknowledges_and_reaches_portal_dashboard(): void
    {
        Storage::fake('supabase');

        $client = $this->client();
        $this->approve($client);
        $this->addSurvey($client);

        $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($client)
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => $signature,
            ])
            ->assertRedirect(route('terms'));

        $this->actingAs($client)
            ->get(route('terms'))
            ->assertOk()
            ->assertSee('Signed')
            ->assertSee('Client Signature');

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertOk();
    }

    public function test_approved_client_without_survey_is_sent_to_survey_first(): void
    {
        $client = $this->client();
        $this->approve($client);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertRedirect(route('client.survey.show'));
    }

    public function test_rejected_client_is_blocked_and_sees_the_reason(): void
    {
        $client = $this->client(['declined_at' => now(), 'decline_reason' => 'Incomplete documents submitted.']);

        $this->actingAs($client)
            ->get(route('client.dashboard'))
            ->assertRedirect(route('client.pending-approval'));

        $this->actingAs($client)
            ->get(route('client.pending-approval'))
            ->assertOk()
            ->assertSee('Account Not Approved')
            ->assertSee('Incomplete documents submitted.');
    }

    public function test_admin_approves_a_pending_client(): void
    {
        Storage::fake('supabase');

        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route('admin.clients.approve', $client))
            ->assertRedirect();

        $client->refresh();

        $this->assertNotNull($client->approved_at);
        $this->assertSame($admin->id, $client->approved_by);
        $this->assertTrue($client->isAccountApproved());

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'account',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.client_approved',
        ]);
    }

    public function test_admin_rejects_a_pending_client_with_reason(): void
    {
        Storage::fake('supabase');

        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->post(route('admin.clients.reject', $client), ['reason' => 'Requirements not complete.'])
            ->assertRedirect();

        $client->refresh();

        $this->assertNull($client->approved_at);
        $this->assertNotNull($client->declined_at);
        $this->assertSame($admin->id, $client->declined_by);
        $this->assertSame('Requirements not complete.', $client->decline_reason);
        $this->assertTrue($client->isAccountRejected());

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'account',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'admin.client_rejected',
        ]);
    }

    public function test_reject_requires_a_reason(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->from(route('admin.clients.pending'))
            ->post(route('admin.clients.reject', $client), ['reason' => ''])
            ->assertSessionHasErrors('reason');

        $this->assertNull($client->refresh()->declined_at);
    }

    public function test_staff_and_supervisor_cannot_approve_or_reject(): void
    {
        $staff = $this->staff();
        $supervisor = $this->internal(User::ROLE_SUPERVISOR, 'Approval Supervisor');
        $client = $this->client();

        $this->actingAs($staff)
            ->post(route('admin.clients.approve', $client))
            ->assertForbidden();

        $this->actingAs($supervisor)
            ->post(route('admin.clients.approve', $client))
            ->assertForbidden();

        $this->actingAs($staff)
            ->post(route('admin.clients.reject', $client), ['reason' => 'No.'])
            ->assertForbidden();

        $this->assertNull($client->refresh()->approved_at);
        $this->assertNull($client->refresh()->declined_at);
    }

    public function test_signature_is_recorded_when_acknowledging_policy(): void
    {
        Storage::fake('supabase');

        $staff = User::create([
            'name' => 'Signing Staff',
            'email' => 'signing.staff'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_STAFF,
            'email_verified_at' => now(),
        ]);

        $signature = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $this->actingAs($staff)
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => $signature,
            ])
            ->assertRedirect(route('terms'));

        $staff->refresh();

        $this->assertSame(EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION, $staff->confidentiality_ack_version);
        $this->assertNotNull($staff->confidentiality_acknowledged_at);

        $signatureRecord = Signature::where('user_id', $staff->id)->first();
        $this->assertNotNull($signatureRecord);
        $this->assertSame(User::ROLE_STAFF, $signatureRecord->role);
        $this->assertSame(EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION, $signatureRecord->policy_version);
        $this->assertStringStartsWith('signatures/', $signatureRecord->signature_path);
        Storage::disk('supabase')->assertExists($signatureRecord->signature_path);
    }

    public function test_acknowledging_requires_a_real_png_signature(): void
    {
        $staff = $this->staff();
        $staff->confidentiality_acknowledged_at = null;
        $staff->save();

        $this->actingAs($staff)
            ->from(route('terms'))
            ->post(route('terms.acknowledge.store'), ['agree' => '1', 'signature_data' => 'data:image/png;base64,not-a-png'])
            ->assertSessionHasErrors('signature_data');
    }

    public function test_new_registration_lands_on_pending_approval_after_verification(): void
    {
        $user = User::create([
            'name' => 'New Registrant',
            'email' => 'new.reg'.uniqid().'@gmail.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
        ]);

        VerificationCode::issue($user, '987654');
        session(['verification_user_id' => $user->id]);

        $this->post('/verify-account', ['code' => '987654'])
            ->assertRedirect(route('client.pending-approval'));

        $user->refresh();

        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->approved_at);
        $this->assertTrue($user->isAccountPending());
    }

    public function test_pending_list_page_is_admin_only(): void
    {
        $admin = $this->admin();
        $staff = $this->staff();
        $supervisor = $this->internal(User::ROLE_SUPERVISOR, 'Approval Supervisor');

        $this->actingAs($staff)->get(route('admin.clients.pending'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.clients.pending'))->assertForbidden();

        $client = $this->client();
        $this->actingAs($admin)->get(route('admin.clients.pending'))->assertOk()->assertSee('Awaiting approval');
    }

    public function test_rejected_client_still_sees_nav_links_to_security_and_notifications(): void
    {
        $client = $this->client(['declined_at' => now(), 'decline_reason' => 'Denied.']);

        $this->actingAs($client)
            ->get(route('client.pending-approval'))
            ->assertOk()
            ->assertSee('Account Not Approved');
    }
}