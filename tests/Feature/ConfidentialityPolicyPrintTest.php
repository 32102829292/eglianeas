<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\Signature;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ConfidentialityPolicyPrintTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'Policy Print Admin');
    }

    private function signaturePng(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    }

    public function test_policy_print_route_loads_for_admin(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.confidentiality.policy'))
            ->assertOk()
            ->assertSee('Confidentiality Policy');
    }

    public function test_policy_print_includes_existing_policy_content(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.confidentiality.policy'))
            ->assertOk()
            ->assertSee('All client information, financial data, and documents accessible through this platform are strictly confidential.')
            ->assertSee('including but not limited to: personal information, financial records, tax documents, uploaded files')
            ->assertSee('Violation of this policy may result in disciplinary action, including termination of your account access.');
    }

    public function test_policy_print_includes_data_privacy_act_ra_10173(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.confidentiality.policy'))
            ->assertOk()
            ->assertSee('Republic Act No. 10173')
            ->assertSee('Data Privacy Act of 2012');
    }

    public function test_policy_print_shows_current_policy_version(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.confidentiality.policy'))
            ->assertOk()
            ->assertSee('Policy Version:', false)
            ->assertSee(EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION);
    }

    public function test_policy_print_contains_no_signature_or_member_records(): void
    {
        Storage::fake('supabase');

        $this->actingAs($this->admin())
            ->post(route('terms.acknowledge.store'), [
                'agree' => '1',
                'signature_data' => $this->signaturePng(),
            ])
            ->assertRedirect(route('terms'));

        $member = User::whereName('Policy Print Admin')->first();
        $this->assertNotNull($member);
        $this->assertNotNull($member->signatures()->first()->signature_path);

        $this->actingAs($this->admin())
            ->get(route('admin.confidentiality.policy'))
            ->assertOk()
            ->assertDontSee('Signature records')
            ->assertDontSee('Signature of Policy Print Admin')
            ->assertDontSee('Signed on')
            ->assertDontSee('Previous policy / no signature')
            ->assertDontSee(route('confidentiality.signature.image', $member->signatures()->first()));
    }

    public function test_policy_print_hides_member_names_and_signature_details(): void
    {
        $this->internal(User::ROLE_STAFF, 'Super Secret Staff');

        $this->actingAs($this->admin())
            ->get(route('admin.confidentiality.policy'))
            ->assertOk()
            ->assertDontSee('Super Secret Staff');
    }

    public function test_policy_print_route_is_admin_only(): void
    {
        $staff = $this->internal(User::ROLE_STAFF, 'Policy Staff');
        $supervisor = $this->internal(User::ROLE_SUPERVISOR, 'Policy Supervisor');

        $this->actingAs($staff)->get(route('admin.confidentiality.policy'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.confidentiality.policy'))->assertForbidden();
    }

    public function test_signature_log_route_and_nav_entry_are_removed(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get('/admin/confidentiality/signatures')
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('terms'))
            ->assertOk()
            ->assertDontSee('Confidentiality Signatures')
            ->assertDontSee('Signature records');
    }

    public function test_signature_image_route_requires_ownership(): void
    {
        Storage::fake('supabase');

        $owner = $this->internal(User::ROLE_STAFF, 'Sig Owner');
        $other = $this->internal(User::ROLE_ADMIN, 'Sig Other');

        $signature = Signature::create([
            'user_id' => $owner->id,
            'policy_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
            'signed_at' => now(),
            'signature_path' => 'signatures/sig.png',
        ]);
        Storage::disk('supabase')->put($signature->signature_path, 'fake-png');

        $this->actingAs($other)
            ->get(route('confidentiality.signature.image', $signature))
            ->assertForbidden();

        $response = $this->actingAs($owner)->get(route('confidentiality.signature.image', $signature));

        $this->assertTrue(
            in_array($response->getStatusCode(), [200, 302], true),
            'Expected the signature owner to be able to view their own signature.'
        );

        auth()->logout();

        $this->get(route('confidentiality.signature.image', $signature))
            ->assertRedirect(route('login'));
    }
}