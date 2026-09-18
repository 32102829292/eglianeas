<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\User;
use App\Models\WebauthnCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileSecuritySummaryTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'Profile Admin');
    }

    private function staff(): User
    {
        return $this->internal(User::ROLE_STAFF, 'Profile Staff');
    }

    private function credentialFor(User $user): WebauthnCredential
    {
        return WebauthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => base64_encode('cred-'.$user->id),
            'name' => 'Windows PC — Biometric login',
            'record' => [
                'publicKeyCredentialId' => base64_encode('cred-'.$user->id),
                'type' => 'public-key',
                'transports' => [],
                'attestationType' => 'none',
                'aaguid' => '00000000-0000-0000-0000-000000000000',
                'credentialPublicKey' => base64_encode('public-key-bytes'),
                'userHandle' => base64_encode((string) $user->id),
                'counter' => 0,
            ],
        ]);
    }

    public function test_admin_profile_shows_account_information(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/profile');

        $response->assertOk();
        $response->assertSee('My profile');
        $response->assertSee($admin->name);
        $response->assertSee($admin->email);
        $response->assertSee('Admin');
        $response->assertSee('Member since');
        $response->assertSee('Account information');
        $response->assertSee('Edit');
    }

    public function test_admin_profile_shows_only_a_compact_security_summary(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/profile');

        $response->assertOk();
        $response->assertSee('Security', false);
        $response->assertSee('Login &amp; security', false);
        $response->assertSee('PIN login');
        $response->assertSee('Not set');
        $response->assertSee('Face / Biometric');
        $response->assertSee('Not enabled');
        $response->assertSee('Security settings');

        $href = route('security.index');
        $response->assertSee(htmlspecialchars($href, ENT_QUOTES), false);
    }

    public function test_admin_profile_does_not_render_the_full_security_form(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/admin/profile');

        $response->assertOk();
        $response->assertDontSee('id="current_password"', false);
        $response->assertDontSee('name="current_password"', false);
        $response->assertDontSee('id="pin"', false);
        $response->assertDontSee('name="pin"', false);
        $response->assertDontSee('id="pin_confirmation"', false);
        $response->assertDontSee('name="pin_confirmation"', false);
        $response->assertDontSee('Update PIN');
        $response->assertDontSee('Enable Face / Biometric login');
        $response->assertDontSee('id="enrollBiometric"', false);
        $response->assertDontSee('id="biometricStatus"', false);
        $response->assertDontSee('data-delete-credential', false);
        $response->assertDontSee('Credential', false);
    }

    public function test_admin_profile_marks_pin_and_biometric_as_set_when_present(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['pin' => Hash::make('1234'), 'pin_set_at' => now()])->save();
        $this->credentialFor($admin);

        $response = $this->actingAs($admin)->get('/admin/profile');

        $response->assertOk();
        $response->assertSee('Login &amp; security', false);
        $response->assertSee('Set');
        $response->assertSee('Enabled');
        $response->assertSee('Your account uses a PIN and supported device authentication for secure sign-in.');

        $response->assertDontSee('id="pin"', false);
        $response->assertDontSee('Update PIN');
        $response->assertDontSee('id="enrollBiometric"', false);
    }

    public function test_staff_profile_shows_compact_security_summary_without_security_forms(): void
    {
        $staff = $this->staff();

        $response = $this->actingAs($staff)->get('/admin/profile');

        $response->assertOk();
        $response->assertSee($staff->name);
        $response->assertSee('Staff');
        $response->assertSee('Login &amp; security', false);
        $response->assertSee('Not set');
        $response->assertSee('Not enabled');
        $response->assertSee('Security settings');

        $response->assertDontSee('id="current_password"', false);
        $response->assertDontSee('id="pin"', false);
        $response->assertDontSee('id="pin_confirmation"', false);
        $response->assertDontSee('Update PIN');
        $response->assertDontSee('Enable Face / Biometric login');
        $response->assertDontSee('id="enrollBiometric"', false);
    }

    public function test_security_settings_button_points_to_the_security_page(): void
    {
        $staff = $this->staff();

        $this->actingAs($staff)->get('/admin/profile')
            ->assertSee('href="'.route('security.index').'"', false)
            ->assertOk();

        $response = $this->actingAs($staff)->get(route('security.index'));
        $response->assertOk();
        $response->assertSee('Security center');
        $response->assertSee('Security settings');
        $response->assertSee('PIN login');
        $response->assertSee('Face / Biometric login');
    }

    public function test_security_settings_page_still_has_the_full_security_functionality(): void
    {
        $admin = $this->admin();
        $admin->forceFill(['pin' => Hash::make('1234'), 'pin_set_at' => now()])->save();

        $response = $this->actingAs($admin)->get('/security');

        $response->assertOk();
        $response->assertSee('PIN login');
        $response->assertSee('id="current_password"', false);
        $response->assertSee('name="pin"', false);
        $response->assertSee('id="pin_confirmation"', false);
        $response->assertSee('Update PIN');
        $response->assertSee('Face / Biometric login');
        $response->assertSee('id="enrollBiometric"', false);
        $response->assertSee('id="bioBadge"', false);
        $response->assertDontSee('id="testBiometric"', false);

        $this->credentialFor($admin);

        $re = $this->actingAs($admin)->get('/security');
        $re->assertSee('id="bioBadge"', false);
        $re->assertSee('Enabled');
        $re->assertSee('id="testBiometric"', false);
    }
}