<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\BirFormStatus;
use App\Models\ClientSurveyResponse;
use App\Models\Document;
use App\Models\Notification;
use App\Models\TrackerAssignment;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FullFunctionalityQaTest extends TestCase
{
    use RefreshDatabase;

    // ---------- helpers (mirrors AdminFlowQaTest) ----------

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
        return $this->internal(User::ROLE_ADMIN, 'QA Admin');
    }

    private function staff(): User
    {
        return $this->internal(User::ROLE_STAFF, 'QA Staff');
    }

    private function client(string $label = 'QA Client'): User
    {
        return User::create([
            'name' => $label,
            'email' => 'qa'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function billing(User $client, array $over = []): Billing
    {
        return Billing::create(array_merge([
            'client_id' => $client->id,
            'quarter' => 1,
            'year' => 2026,
            'period_label' => '1ST QUARTER 2026 BILLING',
            'total' => 1000.00,
            'status' => Billing::STATUS_UNPAID,
            'due_date' => now()->addDays(5)->toDateString(),
            'created_by' => $client->id,
            'updated_by' => $client->id,
        ], $over));
    }

    private function completeSurvey(User $client): void
    {
        $this->actingAs($client)->post('/client/survey', [
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => 'QA smoke',
        ])->assertSessionHasNoErrors();
    }

    // ---------- 2. Client management ----------

    public function test_client_create_edit_view_soft_delete(): void
    {
        $admin = $this->admin();
        $email = 'create'.uniqid().'@example.com';

        // create
        $this->actingAs($admin)->post('/admin/clients', [
            'name' => 'Feature Client',
            'email' => $email,
            'business_name' => 'Feature Trading',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertSessionHasNoErrors()->assertRedirect('/admin/clients/'.User::where('email', $email)->value('id'));

        $client = User::where('email', $email)->first();
        $this->assertNotNull($client);
        $this->assertSame(User::ROLE_CLIENT, $client->role);

        // view (index + show)
        $this->actingAs($admin)->get('/admin/clients')
            ->assertOk()->assertSee($client->name);
        $this->actingAs($admin)->get("/admin/clients/{$client->id}")
            ->assertOk();

        // edit
        $newEmail = 'edit'.uniqid().'@example.com';
        $this->actingAs($admin)->put("/admin/clients/{$client->id}", [
            'name' => 'Feature Client Renamed',
            'email' => $newEmail,
            'status' => 'current',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $client->refresh();
        $this->assertSame('Feature Client Renamed', $client->name);
        $this->assertSame($newEmail, $client->email);

        // soft delete
        $this->actingAs($admin)->delete("/admin/clients/{$client->id}")
            ->assertRedirect();
        $this->assertSoftDeleted('users', ['id' => $client->id]);
    }

    // ---------- 1. Billing ----------

    public function test_billing_full_lifecycle_create_edit_pay_receipt_csv(): void
    {
        $admin = $this->admin();
        $client = $this->client('Billing Client');

        // create
        $this->actingAs($admin)->post('/admin/billings', [
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'due_date' => now()->addDays(10)->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.billing.index'));

        $billing = Billing::where('client_id', $client->id)->first();
        $this->assertNotNull($billing);
        $this->assertSame(Billing::STATUS_UNPAID, $billing->status);
        $this->assertNotNull($billing->period_label);

        // edit
        $this->actingAs($admin)->put("/admin/billings/{$billing->id}", [
            'client_id' => $client->id,
            'quarter' => 2,
            'year' => 2026,
            'due_date' => now()->addDays(12)->toDateString(),
        ])->assertSessionHasNoErrors()->assertRedirect();

        // mark paid
        $this->actingAs($admin)->post("/admin/billings/{$billing->id}/pay", [
            'status' => Billing::STATUS_PAID,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $billing->refresh();
        $this->assertSame(Billing::STATUS_PAID, $billing->status);
        $this->assertNotNull($billing->paid_at);

        // receipt (requires paid)
        $this->actingAs($admin)->get("/admin/billings/{$billing->id}/receipt")
            ->assertOk();

        // CSV export
        $this->actingAs($admin)->get("/admin/billings/{$billing->id}/csv")
            ->assertOk();
    }

    // ---------- 3. Document distribution ----------

    public function test_document_upload_view_download(): void
    {
        $admin = $this->admin();
        $client = $this->client('Doc Client');
        Storage::fake('supabase');

        $file = UploadedFile::fake()->create('cor.pdf', 10, 'application/pdf');

        $this->actingAs($admin)->post("/admin/distribution/{$client->id}/softcopy", [
            'form_type' => '2551Q',
            'file' => $file,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $doc = Document::where('client_id', $client->id)->first();
        $this->assertNotNull($doc);

        // view page
        $this->actingAs($admin)->get("/admin/distribution/{$doc->id}/view")
            ->assertOk();

        // download
        $this->actingAs($admin)->get(route('admin.distribution.download', $doc))
            ->assertOk();
    }

    // ---------- 4. BIR forms ----------

    public function test_bir_form_status_tracking(): void
    {
        $admin = $this->admin();
        $client = $this->client('Bir Client');

        $this->actingAs($admin)->post("/admin/distribution/{$client->id}/bir-status", [
            'form_type' => '2551Q',
            'status' => BirFormStatus::STATUS_FILED,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $status = BirFormStatus::where('client_id', $client->id)->where('form_type', '2551Q')->first();
        $this->assertNotNull($status);
        $this->assertSame(BirFormStatus::STATUS_FILED, $status->status);

        // bir-forms index page renders
        $this->actingAs($admin)->get('/admin/bir-forms')->assertOk();

        // export endpoints respond (file)
        $this->actingAs($admin)->get('/admin/bir-forms/export/pdf')->assertOk();
        $this->actingAs($admin)->get('/admin/bir-forms/export/xlsx')->assertOk();
    }

    // ---------- 5. Service tracker ----------

    public function test_service_tracker_instance_assign_toggle_summary(): void
    {
        $admin = $this->admin();
        $client = $this->client('Tracker Client');
        $assigned = $this->staff();

        $service = TrackerService::create(['name' => 'BIR Registration']);

        // create instance with staff assignment
        $this->actingAs($admin)->post('/admin/service-tracker', [
            'client_id' => $client->id,
            'service_id' => $service->id,
            'date_identified' => now()->toDateString(),
            'staff_names' => [$assigned->name],
            'notes' => 'QA smoke note',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $instance = TrackerInstance::where('client_id', $client->id)->first();
        $this->assertNotNull($instance);
        $this->assertSame(TrackerInstance::STATUS_TODO, $instance->status);

        $assignment = $instance->assignments()->first();
        $this->assertNotNull($assignment);

        // toggle done
        $this->actingAs($admin)->post("/admin/service-tracker/assignment/{$assignment->id}/toggle")
            ->assertRedirect();
        $this->assertTrue($assignment->fresh()->completed);

        // summary page (admin only)
        $this->actingAs($admin)->get('/admin/service-tracker/summary')->assertOk();
        $this->actingAs($this->staff())->get('/admin/service-tracker/summary')->assertForbidden();
    }

    // ---------- 6. Collections & follow-ups ----------

    public function test_collection_remind_sends_notification(): void
    {
        $admin = $this->admin();
        $client = $this->client('Collection Client');
        $billing = $this->billing($client, ['status' => Billing::STATUS_UNPAID]);

        $this->actingAs($admin)->post("/admin/collections/{$billing->id}/remind")
            ->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $client->id,
            'type' => 'billing_due',
        ]);

        // collections page renders
        $this->actingAs($admin)->get('/admin/collections')->assertOk();
    }

    // ---------- 7. Announcements ----------

    public function test_announcement_create_and_client_sees_it(): void
    {
        $admin = $this->admin();
        $client = $this->client('Announce Client');

        $this->actingAs($admin)->post('/admin/announcements', [
            'title' => 'QA announcement title',
            'body' => 'QA announcement body text for smoke test.',
        ])->assertSessionHasNoErrors()->assertRedirect('/');

        $this->assertDatabaseHas('announcements', [
            'title' => 'QA announcement title',
            'body' => 'QA announcement body text for smoke test.',
        ]);

        // client sees it on the home page
        $this->actingAs($client)->get('/')
            ->assertOk()
            ->assertSee('QA announcement title');
    }

    // ---------- 8. Client survey 30-day gate ----------

    public function test_survey_30day_gate_blocks_and_allows(): void
    {
        $client = $this->client('Survey Client');

        // no recent response -> survey due -> form shown
        $this->assertTrue($client->monthlySurveyDue());
        $this->actingAs($client)->get('/client/survey')
            ->assertOk()
            ->assertSee('overall_rating');

        // submit survey
        $this->actingAs($client)->post('/client/survey', [
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 4,
            'comments' => 'QA feedback',
        ])->assertSessionHasNoErrors()->assertRedirect(route('client.dashboard'));

        $this->assertDatabaseHas('client_survey_responses', [
            'user_id' => $client->id,
            'overall_rating' => 5,
        ]);

        // now a response exists within 30 days -> gate blocks -> redirect to dashboard
        $this->assertFalse($client->fresh()->monthlySurveyDue());
        $this->actingAs($client)->get('/client/survey')
            ->assertRedirect(route('client.dashboard'));
    }

    // ---------- 9. Push notifications ----------

    public function test_push_test_flow_no_subscription_returns_informative_403(): void
    {
        $client = $this->client('Push Client');
        $this->completeSurvey($client);

        // no device subscription -> 403 JSON with explanation (not a crash)
        $this->actingAs($client)->postJson('/push/test')
            ->assertStatus(403)
            ->assertJson(['sent' => false]);
    }

    // ---------- 10. Geocoding ----------

    public function test_geocoding_resolves_address_via_locationiq(): void
    {
        $this->actingAs($this->admin());

        Http::fake([
            'us1.locationiq.com/v1/search*' => Http::response([
                ['lat' => '10.3157', 'lon' => '123.8854', 'display_name' => 'Cebu City, Philippines'],
            ], 200),
        ]);

        $response = $this->postJson('/admin/distribution/geocode', ['q' => 'Cebu City']);
        $response->assertOk()
            ->assertJsonPath('lat', 10.3157)
            ->assertJsonPath('lng', 123.8854);
    }

    // ---------- 11. Admin dashboard ----------

    public function test_admin_dashboard_renders_with_data(): void
    {
        $admin = $this->admin();
        $client = $this->client('Dash Client');
        $this->billing($client);
        $this->billing($client, [
            'quarter' => 2,
            'period_label' => '2ND QUARTER 2026 BILLING',
            'status' => Billing::STATUS_PAID,
            'paid_at' => now()->subDay(),
        ]);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('stat-card', false);
    }

    // ---------- 12. Client dashboard / portal ----------

    public function test_client_dashboard_and_portal_render(): void
    {
        $client = $this->client('Portal Client');
        $client->getClientProfile();
        $this->completeSurvey($client);

        $this->actingAs($client)->get('/client/dashboard')->assertOk();
        $this->actingAs($client)->get('/client/profile')->assertOk();
    }
}
