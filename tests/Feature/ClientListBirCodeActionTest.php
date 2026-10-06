<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\BirFormType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The "+ Add BIR Code" action on the Client List page (/admin/clients).
 *
 * It sits beside the existing BIR Codes filter and opens the *existing* BIR
 * form-type creation modal (admin.bir-form-types.store) rather than a new flow.
 * Only admins may use it, matching the store endpoint's own authorization — a
 * staff member offered the button would only be able to trigger a 403. The
 * existing BIR Codes filter itself must keep working unchanged.
 */
class ClientListBirCodeActionTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'BIR Code Admin');
    }

    // ------------------------------------------------------------------
    // Display / visibility
    // ------------------------------------------------------------------

    public function test_admins_are_offered_the_add_bir_code_action(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->getContent();

        // The action and its modal exist for admins...
        $this->assertStringContainsString('id="addBirCodeModal"', $html);
        $this->assertStringContainsString('data-bs-target="#addBirCodeModal"', $html);
        $this->assertStringContainsString('bir-add-code-btn', $html);
        $this->assertStringContainsString('Add BIR Code', $html);

        // ...and they reuse the existing creation flow, not a new endpoint.
        $this->assertStringContainsString(route('admin.bir-form-types.store'), $html);
    }

    /**
     * The button must sit beside the BIR Codes filter, not replace it: the
     * existing filter dropdown is still rendered and the action sits between
     * that control and the rest of the toolbar.
     */
    public function test_the_action_is_placed_beside_the_bir_codes_filter(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->getContent();

        $filter = strpos($html, 'id="bir-code-filter"');
        $action = strpos($html, 'bir-add-code-btn');
        $download = strpos($html, 'id="download-menu"');

        $this->assertNotFalse($filter, 'The existing BIR Codes filter must remain.');
        $this->assertNotFalse($action, 'The Add BIR Code action must be rendered.');
        $this->assertNotFalse($download);
        $this->assertTrue($filter < $action, 'The action must come after the BIR Codes filter control.');
        $this->assertTrue($action < $download, 'The action must stay within the filter toolbar.');
    }

    public function test_the_existing_bir_codes_filter_is_preserved(): void
    {
        $html = $this->actingAs($this->admin())
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="bir-code-filter"', $html);
        $this->assertStringContainsString('name="bir_codes[]"', $html);
        $this->assertStringContainsString('data-bir-summary', $html);
    }

    public function test_supervisor_is_offered_the_action_but_staff_is_not(): void
    {
        $supervisorHtml = $this->actingAs($this->internal(User::ROLE_SUPERVISOR, 'BIR Code Supervisor'))
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('id="addBirCodeModal"', $supervisorHtml);
        $this->assertStringContainsString('bir-add-code-btn', $supervisorHtml);
        $this->assertStringContainsString('id="bir-code-filter"', $supervisorHtml);

        $staffHtml = $this->actingAs($this->internal(User::ROLE_STAFF, 'BIR Code Staff'))
            ->get(route('admin.clients.index'))
            ->assertOk()
            ->getContent();

        $this->assertStringNotContainsString('id="addBirCodeModal"', $staffHtml, 'Staff must not be offered the Add BIR Code modal.');
        $this->assertStringNotContainsString('bir-add-code-btn', $staffHtml, 'Staff must not be offered the Add BIR Code action.');

        // The existing filter is not restricted and must remain available to staff.
        $this->assertStringContainsString('id="bir-code-filter"', $staffHtml, 'Staff must still see the BIR Codes filter.');
    }

    public function test_a_supervisor_can_create_a_bir_code_and_staff_cannot(): void
    {
        $supervisor = $this->internal(User::ROLE_SUPERVISOR, 'BIR Code Supervisor');

        $this->actingAs($supervisor)
            ->from(route('admin.clients.index'))
            ->post(route('admin.bir-form-types.store'), [
                'code' => 'supcode',
                'name' => 'BIR Form Supervisor Code',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.clients.index'));

        $this->assertDatabaseHas('bir_form_types', ['code' => 'SUPCODE']);

        $staff = $this->internal(User::ROLE_STAFF, 'BIR Code Staff');

        $this->actingAs($staff)
            ->post(route('admin.bir-form-types.store'), [
                'code' => 'staffcode',
                'name' => 'BIR Form Staff Code',
                'active' => '1',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('bir_form_types', ['code' => 'STAFFCODE']);
    }

    // ------------------------------------------------------------------
    // Creation
    // ------------------------------------------------------------------

    public function test_an_admin_can_create_a_bir_code_through_the_existing_flow(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.clients.index'))
            ->post(route('admin.bir-form-types.store'), [
                'code' => 'newcode',
                'name' => 'BIR Form New Code',
                'description' => 'Created from the client list page.',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.clients.index'));

        // The code is normalised to upper case, exactly as the existing flow does.
        $this->assertDatabaseHas('bir_form_types', [
            'code' => 'NEWCODE',
            'name' => 'BIR Form New Code',
            'active' => true,
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $admin->id,
            'action' => 'admin.bir_form_type_created',
        ]);
    }

    public function test_creating_a_bir_code_requires_a_code_and_a_name(): void
    {
        $this->actingAs($this->admin())
            ->from(route('admin.clients.index'))
            ->post(route('admin.bir-form-types.store'), [])
            ->assertSessionHasErrors(['code', 'name']);
    }

    public function test_creating_a_bir_code_rejects_a_duplicate_code(): void
    {
        BirFormType::create([
            'code' => 'DUP1',
            'name' => 'Existing Form',
            'active' => true,
            'sort_order' => 1,
        ]);

        $this->actingAs($this->admin())
            ->from(route('admin.clients.index'))
            ->post(route('admin.bir-form-types.store'), [
                'code' => 'DUP1',
                'name' => 'Another Name',
            ])
            ->assertSessionHasErrors('code');
    }

    public function test_a_non_admin_cannot_create_a_bir_code(): void
    {
        $staff = $this->internal(User::ROLE_STAFF, 'BIR Code Staff');

        $this->actingAs($staff)
            ->post(route('admin.bir-form-types.store'), [
                'code' => 'STAFF1',
                'name' => 'Should Not Exist',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('bir_form_types', ['code' => 'STAFF1']);
    }

    public function test_a_client_cannot_create_a_bir_code(): void
    {
        $client = $this->internal(User::ROLE_CLIENT, 'BIR Code Client');

        $this->actingAs($client)
            ->post(route('admin.bir-form-types.store'), [
                'code' => 'CLIENT1',
                'name' => 'Should Not Exist',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('bir_form_types', ['code' => 'CLIENT1']);
    }
}