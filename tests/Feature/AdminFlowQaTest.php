<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminFlowQaTest extends TestCase
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
        ]);
    }

    private function unpaidBilling(User $client): Billing
    {
        $billing = new Billing;
        $billing->client_id = $client->id;
        $billing->quarter = 1;
        $billing->year = 2026;
        $billing->period_label = '1ST QUARTER 2026 BILLING';
        $billing->cash_in = 0;
        $billing->total = 5327.50;
        $billing->status = Billing::STATUS_UNPAID;
        $billing->due_date = now()->addDays(5)->toDateString();
        $billing->created_by = $client->id;
        $billing->updated_by = $client->id;
        $billing->save();

        return $billing;
    }

    public function test_admin_dashboard_requires_confidentiality_acknowledgement(): void
    {
        $admin = User::create([
            'name' => 'Unacked Admin',
            'email' => 'unacked'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_ADMIN,
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertRedirect(route('admin.confidentiality.acknowledge'));
    }

    public function test_activity_logs_denied_for_staff_and_allowed_for_admin(): void
    {
        $this->actingAs($this->staff())->get('/admin/activity-logs')->assertForbidden();

        $this->actingAs($this->admin())->get('/admin/activity-logs')->assertOk();
    }

    public function test_admin_core_pages_render(): void
    {
        $admin = $this->admin();

        foreach (['/admin/dashboard', '/admin/clients', '/admin/billings', '/admin/billings/create', '/admin/collections', '/admin/bir-forms', '/admin/other-services', '/admin/distribution', '/admin/announcements', '/admin/activity-logs', '/admin/chatbot', '/admin/service-tracker', '/admin/users'] as $path) {
            $this->actingAs($admin)->get($path)->assertOk();
        }
    }

    public function test_distribution_show_loads_leaflet_for_lookup_map_with_and_without_coords(): void
    {
        $client = $this->client('Map Client');
        $client->getClientProfile();

        $response = $this->actingAs($this->admin())->get("/admin/distribution/{$client->id}");
        $response->assertOk();
        $response->assertSee('id="distMapLoader"', false);
        $response->assertSee('vendor/leaflet/leaflet.css');
        $response->assertSee('/js/address-map.js');

        $profile = $client->getClientProfile();
        $profile->latitude = 14.5995;
        $profile->longitude = 121.0419;
        $profile->save();

        $response = $this->actingAs($this->admin())->get("/admin/distribution/{$client->id}");
        $response->assertOk();
        $response->assertSee('id="distMapLoader"', false);
        $response->assertSee('id="clientMap"', false);
    }

    public function test_admin_can_mark_billing_paid_and_client_is_notified(): void
    {
        $admin = $this->admin();
        $client = $this->client('Pay Client');
        $billing = $this->unpaidBilling($client);

        $this->actingAs($admin)->post("/admin/billings/{$billing->id}/pay", [
            'status' => Billing::STATUS_PAID,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $billing->refresh();
        $this->assertSame(Billing::STATUS_PAID, $billing->status);
        $this->assertNotNull($billing->paid_at);
        $this->assertSame(1, Notification::where('user_id', $client->id)->count());
    }

    public function test_staff_cannot_mark_billing_paid(): void
    {
        $client = $this->client('Staff Pay Client');
        $billing = $this->unpaidBilling($client);

        $this->actingAs($this->staff())->post("/admin/billings/{$billing->id}/pay", [
            'status' => Billing::STATUS_PAID,
        ])->assertForbidden();

        $billing->refresh();
        $this->assertSame(Billing::STATUS_UNPAID, $billing->status);
        $this->assertNull($billing->paid_at);
    }

    public function test_staff_cannot_unpay_a_paid_billing_either(): void
    {
        $client = $this->client('No Unpay Client');
        $billing = $this->unpaidBilling($client);
        $billing->status = Billing::STATUS_PAID;
        $billing->paid_at = now();
        $billing->save();

        $this->actingAs($this->staff())->post("/admin/billings/{$billing->id}/pay", [
            'status' => Billing::STATUS_UNPAID,
        ])->assertForbidden();
    }

    public function test_masterlist_exports_return_files_for_admin(): void
    {
        $this->client('Export Client');

        $xlsx = $this->actingAs($this->admin())->get('/admin/clients/export/xlsx');
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', $xlsx->headers->get('content-type') ?? '');

        $pdf = $this->actingAs($this->admin())->get('/admin/clients/export/pdf');
        $pdf->assertOk();
        $this->assertStringContainsString('pdf', $pdf->headers->get('content-type') ?? '');
    }

    public function test_admin_users_page_hides_delete_for_staff(): void
    {
        $this->client('Delete Me');
        $admin = $this->admin();
        $staff = $this->staff();

        $this->actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSee('account? This can be undone by support.');

        $this->actingAs($staff)->get('/admin/users')
            ->assertOk()
            ->assertDontSee('account? This can be undone by support.');
    }

    public function test_impersonate_flow_starts_and_stops(): void
    {
        $admin = $this->admin();
        $client = $this->client('Demo Client');

        $this->actingAs($admin)->post("/admin/clients/{$client->id}/impersonate")
            ->assertRedirect(route('client.dashboard'));

        $this->assertSame($client->id, \Illuminate\Support\Facades\Auth::id());
        $this->assertSame($admin->id, session('impersonator_id'));

        $this->post('/admin/impersonate/stop')
            ->assertRedirect();

        $this->assertSame($admin->id, \Illuminate\Support\Facades\Auth::id());
        $this->assertNull(session('impersonator_id'));
    }

    public function test_notifications_pages_render_for_client(): void
    {
        $client = $this->client('Notif Client');

        $this->actingAs($client)->get('/notifications')->assertOk();
    }

    public function test_admin_clients_show_renders_cor_section(): void
    {
        $client = $this->client('Profile Client');
        $profile = $client->getClientProfile();
        $profile->business_type = 'Corporation';
        $profile->save();

        $this->actingAs($this->admin())->get("/admin/clients/{$client->id}")
            ->assertOk();
    }
}