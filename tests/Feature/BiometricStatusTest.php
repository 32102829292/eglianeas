<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\User;
use App\Models\WebauthnCredential;
use App\Support\WebauthnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

class BiometricStatusTest extends TestCase
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
        return $this->internal(User::ROLE_ADMIN, 'Bio Admin');
    }

    private function staff(): User
    {
        return $this->internal(User::ROLE_STAFF, 'Bio Staff');
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

    private function binaryId(User $user): string
    {
        return base64_decode(base64_encode('cred-'.$user->id), true);
    }

    public function test_admin_sees_not_enabled_when_no_credential_exists(): void
    {
        $response = $this->actingAs($this->admin())->get('/security');

        $response->assertOk();
        $response->assertSee('Not enabled');
        $response->assertSee('No biometric credential is registered on this account or device.');
        $response->assertDontSee('id="testBiometric"', false);
        $response->assertSee('id="enrollBiometric"', false);
    }

    public function test_staff_sees_not_enabled_when_no_credential_exists(): void
    {
        $response = $this->actingAs($this->staff())->get('/security');

        $response->assertOk();
        $response->assertSee('Not enabled');
        $response->assertSee('No biometric credential is registered on this account or device.');
        $response->assertDontSee('id="testBiometric"', false);
        $response->assertSee('id="enrollBiometric"', false);
    }

    public function test_admin_sees_enabled_when_credential_exists(): void
    {
        $admin = $this->admin();
        $this->credentialFor($admin);

        $response = $this->actingAs($admin)->get('/security');

        $response->assertOk();
        $response->assertSee('Enabled');
        $response->assertSee('A biometric credential is registered for this account.');
        $response->assertSee('id="testBiometric"', false);
    }

    public function test_staff_sees_enabled_when_credential_exists(): void
    {
        $staff = $this->staff();
        $this->credentialFor($staff);

        $response = $this->actingAs($staff)->get('/security');

        $response->assertOk();
        $response->assertSee('Enabled');
        $response->assertSee('A biometric credential is registered for this account.');
        $response->assertSee('id="testBiometric"', false);
    }

    public function test_test_button_only_appears_when_enabled(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/security')->assertDontSee('id="testBiometric"', false);

        $this->credentialFor($admin);

        $this->actingAs($admin)->get('/security')->assertSee('id="testBiometric"', false);
    }

    public function test_unauthenticated_user_cannot_request_test_options(): void
    {
        $this->postJson('/webauthn/test/options')->assertStatus(401);
    }

    public function test_test_options_require_a_registered_credential(): void
    {
        $response = $this->actingAs($this->admin())->postJson('/webauthn/test/options');

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'No biometric credential is registered on this account yet. Enable Face / Biometric login first.');
    }

    public function test_test_options_return_own_credentials_only(): void
    {
        $admin = $this->admin();
        $this->credentialFor($admin);
        $this->credentialFor($this->staff());

        $response = $this->actingAs($admin)->postJson('/webauthn/test/options');

        $response->assertOk();
        $response->assertJsonStructure(['challenge', 'rpId', 'allowCredentials' => [['type', 'id']]]);
        $allow = $response->json('allowCredentials');
        $this->assertCount(1, $allow);
        $this->assertSame('public-key', $allow[0]['type']);
    }

    public function test_user_cannot_test_another_users_credential(): void
    {
        $admin = $this->admin();
        $other = $this->staff();
        $otherCred = $this->credentialFor($other);

        $response = $this->actingAs($admin)->postJson('/webauthn/test/verify', [
            'credential' => ['rawId' => $otherCred->credential_id],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'This biometric credential is not registered on your account on this device.');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_valid_assertion_reports_success_without_creating_a_session(): void
    {
        $admin = $this->admin();
        $this->credentialFor($admin);
        $binaryId = $this->binaryId($admin);

        $credRecord = CredentialRecord::create(
            publicKeyCredentialId: $binaryId,
            type: 'public-key',
            transports: [],
            attestationType: 'none',
            trustPath: new EmptyTrustPath,
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: base64_encode('public-key-bytes'),
            userHandle: base64_encode((string) $admin->id),
            counter: 0,
        );

        $mock = $this->createMock(WebauthnService::class);
        $mock->method('recordFromCredential')->willReturn($credRecord);
        $mock->method('verifyRequest')->willReturn(1);
        $this->app->instance(WebauthnService::class, $mock);

        $response = $this->actingAs($admin)->postJson('/webauthn/test/verify', [
            'credential' => ['rawId' => base64_encode($binaryId)],
        ]);

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $this->assertAuthenticatedAs($admin);
        // No new credential row is ever created by a test ceremony, and the existing
        // stored credential is never mutated.
        $this->assertSame(1, WebauthnCredential::where('user_id', $admin->id)->count());
        $this->assertSame('Windows PC — Biometric login', $admin->webauthnCredentials()->first()->name);
    }

    public function test_failed_biometric_verification_does_not_enable_the_account(): void
    {
        $admin = $this->admin();
        // No credential exists on this account — an assertion for a credential
        // registered on some other device/browser must fail closed and must not
        // mark the account as enabled.
        $other = $this->staff();
        $foreignId = $this->credentialFor($other)->credential_id;

        $response = $this->actingAs($admin)->postJson('/webauthn/test/verify', [
            'credential' => ['rawId' => $foreignId],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'This biometric credential is not registered on your account on this device.');
        $this->assertSame(0, WebauthnCredential::where('user_id', $admin->id)->count());

        $page = $this->actingAs($admin)->get('/security');
        $page->assertSee('Not enabled');
        $page->assertDontSee('id="testBiometric"', false);
    }

    public function test_expired_or_missing_challenge_is_reported_as_a_friendly_failure(): void
    {
        $admin = $this->admin();
        $this->credentialFor($admin);
        $binaryId = $this->binaryId($admin);

        // No /webauthn/test/options call happened in this session, so the stored
        // assertion challenge is absent/expired and the real server-side ceremony
        // rejects the response.
        $response = $this->actingAs($admin)->postJson('/webauthn/test/verify', [
            'credential' => ['rawId' => base64_encode($binaryId)],
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Biometric verification failed. Please try again.');
        $this->assertSame(1, WebauthnCredential::where('user_id', $admin->id)->count());

        $page = $this->actingAs($admin)->get('/security');
        $page->assertSee('Enabled');
    }

    public function test_no_sensitive_credential_data_is_rendered_in_html(): void
    {
        $admin = $this->admin();
        $this->credentialFor($admin);

        $response = $this->actingAs($admin)->get('/security');

        $response->assertOk();
        $response->assertDontSee('credentialPublicKey');
        $response->assertDontSee('publicKeyCredentialId');
        $response->assertDontSee('userHandle');
        $response->assertDontSee('public-key-bytes');
    }

    public function test_pin_flow_still_works_on_the_security_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/security')->assertOk();

        $response = $this->actingAs($admin)->post('/security/pin', [
            'current_password' => 'secret',
            'pin' => '1234',
            'pin_confirmation' => '1234',
        ]);

        $response->assertRedirect();
        $this->assertNotNull($admin->fresh()->pin);
    }

    public function test_biometric_registration_options_still_available(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->getJson('/webauthn/register/options')
            ->assertOk()
            ->assertJsonStructure(['challenge', 'rp', 'user', 'pubKeyCredParams']);
    }
}