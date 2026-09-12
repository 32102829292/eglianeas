<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\BirFormStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBirFormsToggleTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'BIR Toggle Admin');
    }

    private function staff(): User
    {
        return $this->internal(User::ROLE_STAFF, 'BIR Toggle Staff');
    }

    private function client(): User
    {
        return $this->internal(User::ROLE_CLIENT, 'BIR Toggle Client');
    }

    public function test_json_toggle_applies_then_unapplies_and_logs(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $formType = BirFormStatus::FORM_TYPES[0];

        $response = $this->actingAs($admin)
            ->postJson("/admin/bir-forms/{$client->id}/toggle", ['form_type' => $formType]);

        $response->assertOk()
            ->assertJson(['ok' => true, 'form_type' => $formType, 'applicable' => true]);

        $this->assertDatabaseHas('bir_form_statuses', [
            'client_id' => $client->id,
            'form_type' => $formType,
            'applicable' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.bir_form_toggled',
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/admin/bir-forms/{$client->id}/toggle", ['form_type' => $formType]);

        $response->assertOk()
            ->assertJson(['ok' => true, 'form_type' => $formType, 'applicable' => false]);

        $this->assertDatabaseHas('bir_form_statuses', [
            'client_id' => $client->id,
            'form_type' => $formType,
            'applicable' => false,
        ]);
    }

    public function test_toggle_without_json_redirects_with_status_flash(): void
    {
        $admin = $this->admin();
        $client = $this->client();
        $formType = BirFormStatus::FORM_TYPES[0];

        $this->actingAs($admin)
            ->post("/admin/bir-forms/{$client->id}/toggle", ['form_type' => $formType])
            ->assertRedirect()
            ->assertSessionHas('status', "{$formType} marked as applicable.");
    }

    public function test_staff_may_toggle_a_client_form(): void
    {
        $staff = $this->staff();
        $client = $this->client();
        $formType = BirFormStatus::FORM_TYPES[1];

        $this->actingAs($staff)
            ->postJson("/admin/bir-forms/{$client->id}/toggle", ['form_type' => $formType])
            ->assertOk()
            ->assertJson(['ok' => true, 'applicable' => true]);
    }

    public function test_toggle_requires_authenticated_admin_or_staff(): void
    {
        $client = $this->client();

        $this->post("/admin/bir-forms/{$client->id}/toggle", ['form_type' => BirFormStatus::FORM_TYPES[0]])
            ->assertRedirect(route('login'));

        $this->actingAs($client)
            ->postJson("/admin/bir-forms/{$client->id}/toggle", ['form_type' => BirFormStatus::FORM_TYPES[0]])
            ->assertForbidden();
    }

    public function test_toggle_non_client_returns_404(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("/admin/bir-forms/{$admin->id}/toggle", ['form_type' => BirFormStatus::FORM_TYPES[0]])
            ->assertNotFound();
    }

    public function test_toggle_invalid_form_type_fails_validation(): void
    {
        $admin = $this->admin();
        $client = $this->client();

        $this->actingAs($admin)
            ->postJson("/admin/bir-forms/{$client->id}/toggle", ['form_type' => 'NOT-A-REAL-FORM'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('form_type');
    }
}