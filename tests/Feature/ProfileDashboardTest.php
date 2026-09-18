<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\Billing;
use App\Models\Document;
use App\Models\TrackerAssignment;
use App\Models\TrackerInstance;
use App\Models\TrackerService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileDashboardTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'Dashboard Admin');
    }

    private function staff(): User
    {
        return $this->internal(User::ROLE_STAFF, 'Dashboard Staff');
    }

    private function client(): User
    {
        return User::create([
            'name' => 'Demo Client',
            'email' => 'client'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
            'email_verified_at' => now(),
        ]);
    }

    private function billing(User $client, string $status, int $quarter, int $year): Billing
    {
        return Billing::create([
            'client_id' => $client->id,
            'period_label' => strtoupper(Billing::QUARTERS[$quarter]).' QUARTER '.$year.' BILLING',
            'quarter' => $quarter,
            'year' => $year,
            'due_date' => now()->addDays(14),
            'cash_in' => 0,
            'total' => 1000,
            'status' => $status,
            'created_by' => null,
        ]);
    }

    public function test_admin_profile_renders_full_account_dashboard(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $this->billing($client, Billing::STATUS_PAID, 1, 2026);
        $this->billing($client, Billing::STATUS_UNPAID, 2, 2026);
        $this->billing($client, Billing::STATUS_OVERDUE, 3, 2026);
        Document::create(['user_id' => $admin->id, 'client_id' => $client->id, 'name' => '2026 Q1 Return', 'original_name' => 'q1.pdf', 'path' => 'x', 'mime_type' => 'application/pdf', 'size' => 100]);
        Document::create(['user_id' => $admin->id, 'client_id' => $client->id, 'name' => '2026 Q2 Return', 'original_name' => 'q2.pdf', 'path' => 'y', 'mime_type' => 'application/pdf', 'size' => 100]);
        ActivityLog::record($admin, 'admin.profile_updated', 'Updated own profile details.');

        $response = $this->actingAs($admin)->get('/admin/profile');

        $response->assertOk();

        // Header
        $response->assertSee($admin->name);
        $response->assertSee($admin->email);
        $response->assertSee('Admin');
        $response->assertSee('Member since');
        $response->assertSee('Edit Profile');
        $response->assertSee('data-onboarding-replay', false);

        // Account information
        $response->assertSee('Account information');
        $response->assertSee('Full name');
        $response->assertSee('Role');

        // Account activity: real counts + clickable cards
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/metric-num">2<\/span>[\s\S]{0,500}?metric-label">Documents<\/span>/', $html);
        $this->assertMatchesRegularExpression('/metric-num">3<\/span>[\s\S]{0,500}?metric-label">Billing statements<\/span>/', $html);
        $this->assertMatchesRegularExpression('/metric-num">2<\/span>[\s\S]{0,500}?metric-label">Pending items<\/span>/', $html);
        $response->assertDontSee('Assigned services');

        // Quick actions (all role-authorized existing routes)
        foreach ([
            route('admin.billing.index'),
            route('admin.collections.index'),
            route('admin.distribution.index'),
            route('admin.service-tracker.index'),
            route('security.index'),
            route('help'),
        ] as $url) {
            $response->assertSee('href="'.$url.'"', false);
        }

        // Security summary + privacy + recent activity
        $response->assertSee('Login &amp; security', false);
        $response->assertSee('Security settings');
        $response->assertSee('Privacy &amp; confidentiality', false);
        $response->assertSee('Acknowledged');
        $response->assertSee('View Terms &amp; Confidentiality', false);
        $response->assertSee(route('terms'), false);
        $response->assertSee('Recent activity');
        $response->assertSee('Updated own profile details.');
    }

    public function test_admin_profile_has_no_duplicate_security_forms(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/profile');

        $response->assertOk();
        $response->assertDontSee('id="current_password"', false);
        $response->assertDontSee('id="pin"', false);
        $response->assertDontSee('id="pin_confirmation"', false);
        $response->assertDontSee('name="pin"', false);
        $response->assertDontSee('Update PIN');
        $response->assertDontSee('Enable Face / Biometric login');
        $response->assertDontSee('id="enrollBiometric"', false);
    }

    public function test_staff_profile_renders_and_excludes_admin_only_content(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)->get('/admin/profile');

        $response->assertOk();
        $response->assertSee($staff->name);
        $response->assertSee($staff->email);
        $response->assertSee('Staff');
        $response->assertSee('Member since');

        // Same sections and quick actions as admin (no admin-only content).
        $response->assertSee('Account activity');
        $response->assertSee('Quick actions');
        $response->assertSee('Login &amp; security', false);
        $response->assertSee('Privacy &amp; confidentiality', false);
        foreach ([route('admin.billing.index'), route('admin.collections.index'), route('security.index'), route('help')] as $url) {
            $response->assertSee('href="'.$url.'"', false);
        }

        // No admin-only route leaks anywhere in the rendered page (incl. nav).
        $response->assertDontSee(route('admin.activity-logs'), false);
    }

    public function test_staff_assigned_services_metric_uses_only_open_assignments(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $service = TrackerService::create(['name' => 'BIR Registration']);
        $instance = TrackerInstance::create(['service_id' => $service->id, 'client_id' => $client->id, 'status' => TrackerInstance::STATUS_IN_PROGRESS]);
        TrackerAssignment::create(['instance_id' => $instance->id, 'staff_id' => $staff->id, 'staff_name' => $staff->name, 'completed' => false]);
        TrackerAssignment::create(['instance_id' => $instance->id, 'staff_id' => $staff->id, 'staff_name' => $staff->name, 'completed' => true]);

        $response = $this->actingAs($staff)->get('/admin/profile');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/metric-num">1<\/span>[\s\S]{0,500}?metric-label">Assigned services<\/span>/', $response->getContent());
        $response->assertSee('Assigned services');

        $admin = $this->admin();
        $adminResponse = $this->actingAs($admin)->get('/admin/profile');
        $adminResponse->assertOk();
        $adminResponse->assertDontSee('Assigned services');
    }

    public function test_recent_activity_is_omitted_when_no_logs_exist(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)->get('/admin/profile');

        $response->assertOk();
        $response->assertDontSee('Recent activity');
        $response->assertDontSee('activity-timeline', false);
    }

    public function test_edit_mode_and_update_are_preserved(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/profile?edit=1');

        $response->assertOk();
        $response->assertSee('id="name"', false);
        $response->assertSee('id="email"', false);
        $response->assertSee('Save changes');

        $updated = $this->actingAs($admin)->patch('/admin/profile', [
            'name' => 'Dashboard Admin Jr',
            'email' => $admin->email,
        ]);

        $updated->assertRedirect(route('admin.profile.index'));
        $this->assertSame('Dashboard Admin Jr', $admin->fresh()->name);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.profile_updated',
        ]);
    }

    public function test_business_name_and_team_member_rows_are_optional_real_data(): void
    {
        $staff = $this->staff();
        $staff->forceFill(['business_name' => 'Egliane Accounting Services'])->save();
        $team = \App\Models\TeamMember::create([
            'user_id' => $staff->id,
            'position' => 'Accounting Associate',
            'department' => 'Bookkeeping',
            'name' => $staff->name,
            'rank' => 'Associate',
            'duties' => 'Bookkeeping support',
        ]);

        $response = $this->actingAs($staff)->get('/admin/profile');

        $response->assertOk();
        $response->assertSee('Business name');
        $response->assertSee('Egliane Accounting Services');
        $response->assertSee('Position');
        $response->assertSee('Accounting Associate');
        $response->assertSee('Department');
        $response->assertSee('Bookkeeping');
        $this->assertSame($staff->id, $team->user_id);
    }
}