<?php

namespace App\Support;

use App\Models\User;
use App\Models\WebauthnCredential;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Symfony\Component\Uid\Uuid;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\Exception\WebauthnException;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialDescriptor;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\TrustPath\EmptyTrustPath;

class WebauthnService
{
    private const CHALLENGE_TTL_SECONDS = 300;

    private const CHALLENGE_CREATION_SESSION_KEY = 'webauthn.creation_challenge';

    private const CHALLENGE_REQUEST_SESSION_KEY = 'webauthn.request_challenge';

    private CeremonyStepManagerFactory $factory;

    private \Symfony\Component\Serializer\SerializerInterface $serializer;

    public function __construct()
    {
        $this->factory = new CeremonyStepManagerFactory();

        $this->factory->setAllowedOrigins([$this->origin()], allowSubdomains: true);

        $attestationSupport = new \Webauthn\AttestationStatement\AttestationStatementSupportManager([
            new \Webauthn\AttestationStatement\NoneAttestationStatementSupport(),
        ]);

        $this->factory->setAttestationStatementSupportManager($attestationSupport);

        $this->serializer = (new WebauthnSerializerFactory($attestationSupport))->create();
    }

    public function rpId(): string
    {
        return parse_url($this->origin(), PHP_URL_HOST) ?? 'localhost';
    }

    public function origin(): string
    {
        return request()->getSchemeAndHttpHost();
    }

    public function creationChallengeKey(): string
    {
        return self::CHALLENGE_CREATION_SESSION_KEY;
    }

    public function requestChallengeKey(): string
    {
        return self::CHALLENGE_REQUEST_SESSION_KEY;
    }

    public function creationOptions(User $user): PublicKeyCredentialCreationOptions
    {
        $challenge = random_bytes(32);

        $options = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create('Egliane Accounting Services', $this->rpId()),
            user: PublicKeyCredentialUserEntity::create(
                name: $user->email,
                id: (string) $user->id,
                displayName: $user->name,
            ),
            challenge: $challenge,
            pubKeyCredParams: [
                PublicKeyCredentialParameters::createPk(ES256::identifier()),
                PublicKeyCredentialParameters::createPk(RS256::identifier()),
            ],
            authenticatorSelection: new AuthenticatorSelectionCriteria(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            timeout: 60000,
        );

        $this->storeChallenge(self::CHALLENGE_CREATION_SESSION_KEY, $challenge);

        return $options;
    }

    public function creationOptionsForBrowser(User $user): array
    {
        $options = $this->creationOptions($user);

        return [
            'rp' => ['name' => 'Egliane Accounting Services', 'id' => $this->rpId()],
            'user' => [
                'name' => $user->email,
                'displayName' => $user->name,
                'id' => $this->b64url((string) $user->id),
            ],
            'challenge' => $this->b64url($options->challenge),
            'pubKeyCredParams' => array_map(
                fn (PublicKeyCredentialParameters $param) => ['type' => $param->type, 'alg' => $param->alg],
                $options->pubKeyCredParams
            ),
            'timeout' => $options->timeout ?? 60000,
            'authenticatorSelection' => [
                'authenticatorAttachment' => 'platform',
                'residentKey' => 'preferred',
                'userVerification' => 'required',
            ],
            'attestation' => 'none',
            'excludeCredentials' => $user->webauthnCredentials
                ->map(fn (WebauthnCredential $credential) => $this->descriptorForBrowser($credential))
                ->values()
                ->all(),
        ];
    }

    public function requestOptions(User $user): PublicKeyCredentialRequestOptions
    {
        $challenge = random_bytes(32);

        $descriptors = $user->webauthnCredentials
            ->map(fn (WebauthnCredential $credential) => $this->recordFromCredential($credential)->getPublicKeyCredentialDescriptor())
            ->values()
            ->all();

        $options = PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId(),
            allowCredentials: $descriptors,
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            timeout: 60000,
        );

        $this->storeChallenge(self::CHALLENGE_REQUEST_SESSION_KEY, $challenge);

        return $options;
    }

    public function requestOptionsForBrowser(User $user): array
    {
        $options = $this->requestOptions($user);

        return [
            'challenge' => $this->b64url($options->challenge),
            'rpId' => $this->rpId(),
            'timeout' => $options->timeout ?? 60000,
            'userVerification' => 'required',
            'allowCredentials' => $user->webauthnCredentials
                ->map(fn (WebauthnCredential $credential) => $this->descriptorForBrowser($credential))
                ->values()
                ->all(),
        ];
    }

    public function verifyCreation(array $clientData, string $userHandle): CredentialRecord
    {
        $options = $this->loadStoredOptions(self::CHALLENGE_CREATION_SESSION_KEY, PublicKeyCredentialCreationOptions::class);

        $credential = $this->deserializeCredential($clientData);

        if (! $credential->response instanceof \Webauthn\AuthenticatorAttestationResponse) {
            throw new WebauthnException('Invalid attestation response.');
        }

        $attested = $credential->response->attestationObject->authData->attestedCredentialData;
        if ($attested === null) {
            throw new WebauthnException('No attested credential data.');
        }

        $placeholder = CredentialRecord::create(
            publicKeyCredentialId: $attested->credentialId,
            type: $credential->type,
            transports: $credential->response->transports,
            attestationType: 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: $attested->aaguid,
            credentialPublicKey: $attested->credentialPublicKey ?? '',
            userHandle: $userHandle,
            counter: $credential->response->attestationObject->authData->signCount,
        );

        $this->factory->creationCeremony()->process(
            $placeholder,
            $credential->response,
            $options,
            $userHandle,
            $this->rpId()
        );

        $this->forgetChallenge(self::CHALLENGE_CREATION_SESSION_KEY);

        return $placeholder;
    }

    public function verifyRequest(array $clientData, WebauthnCredential $storedCredential, string $userHandle): int
    {
        $options = $this->loadStoredOptions(self::CHALLENGE_REQUEST_SESSION_KEY, PublicKeyCredentialRequestOptions::class);

        $credential = $this->deserializeCredential($clientData);

        if (! $credential->response instanceof AuthenticatorAssertionResponse) {
            throw new WebauthnException('Invalid assertion response.');
        }

        $record = $this->recordFromCredential($storedCredential);

        $this->factory->requestCeremony()->process(
            $record,
            $credential->response,
            $options,
            $userHandle,
            $this->rpId()
        );

        $this->forgetChallenge(self::CHALLENGE_REQUEST_SESSION_KEY);

        return $credential->response->authenticatorData->signCount;
    }

    public function recordToArray(CredentialRecord $record): array
    {
        return [
            'publicKeyCredentialId' => base64_encode($record->publicKeyCredentialId),
            'type' => $record->type,
            'transports' => $record->transports,
            'attestationType' => $record->attestationType,
            'aaguid' => $record->aaguid->toString(),
            'credentialPublicKey' => base64_encode($record->credentialPublicKey),
            'userHandle' => base64_encode($record->userHandle),
            'counter' => $record->counter,
        ];
    }

    public function recordFromArray(array $data): CredentialRecord
    {
        return CredentialRecord::create(
            publicKeyCredentialId: base64_decode($data['publicKeyCredentialId'], true),
            type: $data['type'] ?? 'public-key',
            transports: $data['transports'] ?? [],
            attestationType: $data['attestationType'] ?? 'none',
            trustPath: EmptyTrustPath::create(),
            aaguid: Uuid::fromString($data['aaguid']),
            credentialPublicKey: base64_decode($data['credentialPublicKey'], true),
            userHandle: base64_decode($data['userHandle'], true),
            counter: $data['counter'] ?? 0,
        );
    }

    public function recordFromCredential(WebauthnCredential $credential): CredentialRecord
    {
        return $this->recordFromArray($credential->record);
    }

    public function deviceNameFromUserAgent(?string $userAgent): string
    {
        $userAgent = $userAgent ?? '';

        $os = 'Device';
        if (preg_match('/iPhone|iPad/i', $userAgent)) {
            $os = 'iPhone';
        } elseif (preg_match('/Android/i', $userAgent)) {
            $os = 'Android';
        } elseif (preg_match('/Mac OS X/i', $userAgent)) {
            $os = 'Mac';
        } elseif (preg_match('/Windows/i', $userAgent)) {
            $os = 'Windows PC';
        } elseif (preg_match('/Linux/i', $userAgent)) {
            $os = 'Linux';
        }

        $capability = 'Biometric login';
        if (preg_match('/Face ID|iPhone/i', $userAgent)) {
            $capability = 'Face ID';
        }

        return $os.' — '.$capability;
    }

    private function descriptorForBrowser(WebauthnCredential $credential): array
    {
        $record = $this->recordFromCredential($credential);

        return [
            'type' => $record->type,
            'id' => $this->b64url($record->publicKeyCredentialId),
            'transports' => $record->transports,
        ];
    }

    private function deserializeCredential(array $clientData): PublicKeyCredential
    {
        return $this->serializer->deserialize(
            json_encode($clientData, JSON_THROW_ON_ERROR),
            PublicKeyCredential::class,
            'json'
        );
    }

    private function storeChallenge(string $key, string $challenge): void
    {
        session([
            $key => base64_encode($challenge),
            $key.'.expires_at' => now()->addSeconds(self::CHALLENGE_TTL_SECONDS)->getTimestamp(),
        ]);
    }

    private function loadStoredOptions(string $key, string $expectedClass): PublicKeyCredentialCreationOptions|PublicKeyCredentialRequestOptions
    {
        $encoded = session($key);
        $expiresAt = session($key.'.expires_at');

        if ($encoded === null || $expiresAt === null || $expiresAt < now()->getTimestamp()) {
            throw new WebauthnException('The security challenge has expired. Please try again.');
        }

        $challenge = base64_decode((string) $encoded, true);

        if ($expectedClass === PublicKeyCredentialCreationOptions::class) {
            return PublicKeyCredentialCreationOptions::create(
                rp: PublicKeyCredentialRpEntity::create('Egliane Accounting Services', $this->rpId()),
                user: PublicKeyCredentialUserEntity::create('', '', ''),
                challenge: $challenge,
                pubKeyCredParams: [
                    PublicKeyCredentialParameters::createPk(ES256::identifier()),
                    PublicKeyCredentialParameters::createPk(RS256::identifier()),
                ],
                authenticatorSelection: new AuthenticatorSelectionCriteria(
                    authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                    userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
                    residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED,
                ),
                attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
            );
        }

        return PublicKeyCredentialRequestOptions::create(
            challenge: $challenge,
            rpId: $this->rpId(),
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        );
    }

    private function forgetChallenge(string $key): void
    {
        session()->forget([$key, $key.'.expires_at']);
    }

    private function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }
}
