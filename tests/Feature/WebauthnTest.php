<?php

namespace Tests\Feature;

use App\Models\ClientSurveyResponse;
use App\Models\User;
use App\Models\WebauthnCredential;
use App\Support\WebauthnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\Uuid;
use Tests\TestCase;
use Webauthn\CredentialRecord;
use Webauthn\TrustPath\EmptyTrustPath;

class WebauthnTest extends TestCase
{
    use RefreshDatabase;

    private function client(): User
    {
        $user = User::create([
            'name' => 'Face Client',
            'email' => 'face'.uniqid().'@example.com',
            'password' => bcrypt('secret'),
            'role' => User::ROLE_CLIENT,
        ]);

        ClientSurveyResponse::create([
            'user_id' => $user->id,
            'overall_rating' => 5,
            'service_rating' => 5,
            'portal_rating' => 5,
            'comments' => null,
            'submitted_at' => now(),
        ]);

        return $user;
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

    public function test_device_name_is_derived_from_user_agent(): void
    {
        $service = $this->app->make(WebauthnService::class);

        $this->assertSame('iPhone — Face ID', $service->deviceNameFromUserAgent('Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 Mobile Safari'));
        $this->assertSame('Windows PC — Biometric login', $service->deviceNameFromUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120 Safari/537.36'));
        $this->assertSame('Android — Biometric login', $service->deviceNameFromUserAgent('Mozilla/5.0 (Linux; Android 13) AppleWebKit/537.36 Mobile Safari'));
        $this->assertSame('Mac — Biometric login', $service->deviceNameFromUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) Safari/605.1.15'));
    }

    public function test_login_options_returns_instructive_message_when_user_has_no_credentials(): void
    {
        $user = $this->client();

        $response = $this->postJson('/login/webauthn/options', [
            'email' => $user->email,
        ]);

        $response->assertStatus(422);
        $response->assertJsonPath('error', 'Face ID isn\'t set up yet. Log in with your PIN, then set up Face ID from Security Settings.');
    }

    public function test_login_options_returns_options_when_user_has_credentials(): void
    {
        $user = $this->client();
        $this->credentialFor($user);

        $response = $this->postJson('/login/webauthn/options', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'challenge',
            'rpId',
            'allowCredentials' => [
                ['type', 'id'],
            ],
        ]);
    }

    public function test_register_options_requires_authentication(): void
    {
        $this->getJson('/webauthn/register/options')->assertStatus(401);
    }

    public function test_authenticated_user_can_request_registration_options(): void
    {
        $user = $this->client();
        $this->actingAs($user);

        $this->getJson('/webauthn/register/options')
            ->assertStatus(200)
            ->assertJsonStructure(['challenge', 'rp', 'user', 'pubKeyCredParams']);
    }

    public function test_unverified_user_cannot_log_in_via_webauthn(): void
    {
        $user = $this->client();
        $credential = $this->credentialFor($user);

        $binaryId = base64_decode('cred-'.$user->id, true);

        $credRecord = CredentialRecord::create(
            publicKeyCredentialId: $binaryId,
            type: 'public-key',
            transports: [],
            attestationType: 'none',
            trustPath: new EmptyTrustPath,
            aaguid: Uuid::fromString('00000000-0000-0000-0000-000000000000'),
            credentialPublicKey: base64_encode('public-key-bytes'),
            userHandle: base64_encode((string) $user->id),
            counter: 0,
        );

        $mock = $this->createMock(WebauthnService::class);
        $mock->method('recordFromCredential')->willReturn($credRecord);
        $mock->method('verifyRequest')->willReturn(1);
        $this->app->instance(WebauthnService::class, $mock);

        $response = $this->withSession(['webauthn.login_email' => $user->email])
            ->postJson('/login/webauthn/verify', [
                'credential' => [
                    'rawId' => base64_encode($binaryId),
                ],
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('unverified', true);
        $response->assertJsonValidationErrors('credential');
        $this->assertNull(Auth::user());
    }
}
