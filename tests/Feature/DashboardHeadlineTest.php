<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Billing;
use App\Models\ClientCompany;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardHeadlineTest extends TestCase
{
    use RefreshDatabase;

    private function internal(string $role, string $label): User
    {
        return User::factory()->create([
            'role' => $role,
            'name' => $label,
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    private function client(string $label = 'Headline Client'): User
    {
        $user = User::factory()->create([
            'role' => User::ROLE_CLIENT,
            'name' => $label,
            'approved_at' => now(),
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);

        ClientCompany::firstOrCreate(
            ['client_id' => $user->id, 'branch_number' => 1],
            ['company_code' => $user->client_code.'-01', 'company_name' => $label, 'business_email' => $user->email]
        );

        return $user;
    }

    private function completeSurvey(User $client): void
    {
        $this->actingAs($client)->post('/client/survey', [
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => 'dashboard headline smoke',
        ])->assertSessionHasNoErrors();
    }

    public function test_admin_dashboard_headline(): void
    {
        $client = $this->client();
        Billing::create([
            'client_id' => $client->id,
            'quarter' => 1,
            'year' => 2026,
            'period_label' => '1ST QUARTER 2026 BILLING',
            'total' => 1000.00,
            'status' => Billing::STATUS_UNPAID,
            'due_date' => now()->addDays(5)->toDateString(),
            'created_by' => $client->id,
            'updated_by' => $client->id,
        ]);

        $this->actingAs($this->internal(User::ROLE_ADMIN, 'Headline Admin'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Accounting Overview', false)
            ->assertSee('Admin Dashboard', false)
            ->assertSee('Manage accounts, oversee operations, and monitor accounting activities.', false)
            ->assertSee('dash-headline-icon', false)
            ->assertSee('<line x1="18" y1="20" x2="18" y2="4"/>', false)
            ->assertDontSee('Supervisor Dashboard', false)
            ->assertDontSee('Staff Dashboard', false);
    }

    public function test_supervisor_dashboard_headline(): void
    {
        $this->actingAs($this->internal(User::ROLE_SUPERVISOR, 'Headline Supervisor'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Team Oversight', false)
            ->assertSee('Supervisor Dashboard', false)
            ->assertSee('Monitor team progress, review daily accomplishments, and keep operations on track.', false)
            ->assertSee('dash-headline-icon', false)
            ->assertSee('<circle cx="9" cy="7" r="4"/>', false)
            ->assertDontSee('>Admin Dashboard<', false)
            ->assertDontSee('Staff Dashboard', false);
    }

    public function test_staff_dashboard_headline(): void
    {
        $this->actingAs($this->internal(User::ROLE_STAFF, 'Headline Staff'))
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Daily Tasks', false)
            ->assertSee('Staff Dashboard', false)
            ->assertSee('Stay organized, manage your daily tasks, and track your accomplishments.', false)
            ->assertSee('dash-headline-icon', false)
            ->assertSee('<path d="M9 11l3 3L22 4"/>', false)
            ->assertDontSee('>Admin Dashboard<', false)
            ->assertDontSee('Supervisor Dashboard', false);
    }

    public function test_client_dashboard_headline(): void
    {
        $client = $this->client();
        $this->completeSurvey($client);

        $this->actingAs($client)
            ->get('/client/dashboard')
            ->assertOk()
            ->assertSee('Account Overview', false)
            ->assertSee('Client Dashboard', false)
            ->assertSee('View your account information, track your services, and stay updated.', false)
            ->assertSee('dash-headline-icon', false)
            ->assertSee('<rect x="2" y="7" width="20" height="14" rx="2"/>', false)
            ->assertDontSee('>Admin Dashboard<', false)
            ->assertDontSee('Supervisor Dashboard', false)
            ->assertDontSee('Staff Dashboard', false);
    }
}