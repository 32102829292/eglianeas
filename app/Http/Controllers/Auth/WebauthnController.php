<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\WebauthnCredential;
use App\Support\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WebauthnController extends Controller
{
    public function options(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        abort_if(session('impersonator_id'), 403, 'Biometric changes are not available while viewing as another user.');

        $options = $webauthn->creationOptionsForBrowser($user);

        return response()->json($options);
    }

    public function verify(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        abort_if(session('impersonator_id'), 403, 'Biometric changes are not available while viewing as another user.');

        $request->validate([
            'credential' => ['required', 'array'],
        ]);

        try {
            $record = $webauthn->verifyCreation($request->input('credential'), (string) $user->id);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'The biometric registration failed. Please try again.'], 422);
        }

        $credentialId = base64_encode($record->publicKeyCredentialId);

        $exists = WebauthnCredential::query()->where('credential_id', $credentialId)->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'credential' => 'This credential has already been registered.',
            ]);
        }

        WebauthnCredential::create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'record' => $webauthn->recordToArray($record),
            'name' => $request->input('name', $webauthn->deviceNameFromUserAgent($request->header('User-Agent'))),
        ]);

        ActivityLog::record($user, 'auth.webauthn_registered', 'Registered a biometric/face login credential.');

        return response()->json(['ok' => true, 'message' => 'Biometric login enabled.']);
    }

    public function testOptions(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        abort_if(session('impersonator_id'), 403, 'Biometric changes are not available while viewing as another user.');

        if (! $user->hasWebauthnCredentials()) {
            return response()->json(['error' => 'No biometric credential is registered on this account yet. Enable Face / Biometric login first.'], 422);
        }

        return response()->json($webauthn->requestOptionsForBrowser($user));
    }

    public function testVerify(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        abort_if(session('impersonator_id'), 403, 'Biometric changes are not available while viewing as another user.');

        $request->validate([
            'credential' => ['required', 'array'],
        ]);

        $rawId = base64_decode(strtr((string) $request->input('credential.rawId'), '-_', '+/'), true);

        $credential = $user->webauthnCredentials->first(function ($item) use ($rawId, $webauthn) {
            $record = $webauthn->recordFromCredential($item);

            return $rawId !== false && hash_equals($record->publicKeyCredentialId, $rawId);
        });

        if ($credential === null) {
            return response()->json(['error' => 'This biometric credential is not registered on your account on this device.'], 422);
        }

        try {
            $webauthn->verifyRequest($request->input('credential'), $credential, (string) $user->id);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Biometric verification failed. Please try again.'], 422);
        }

        return response()->json(['ok' => true, 'message' => 'Biometric verification succeeded.']);
    }

    public function destroy(Request $request, WebauthnService $webauthn, int $id): JsonResponse
    {
        abort_if(session('impersonator_id'), 403, 'Biometric changes are not available while viewing as another user.');

        $credential = WebauthnCredential::query()
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $credential->delete();

        ActivityLog::record(Auth::user(), 'auth.webauthn_removed', 'Removed a biometric/face login credential.');

        return response()->json(['ok' => true]);
    }
}
