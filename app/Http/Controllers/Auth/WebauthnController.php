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

        $options = $webauthn->creationOptionsForBrowser($user);

        return response()->json($options);
    }

    public function verify(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $user = Auth::user();

        if ($user === null) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

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
            'name' => $request->input('name', 'Face / Biometric login'),
        ]);

        ActivityLog::record($user, 'auth.webauthn_registered', 'Registered a biometric/face login credential.');

        return response()->json(['ok' => true, 'message' => 'Biometric login enabled.']);
    }

    public function destroy(Request $request, WebauthnService $webauthn, int $id): JsonResponse
    {
        $credential = WebauthnCredential::query()
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->firstOrFail();

        $credential->delete();

        ActivityLog::record(Auth::user(), 'auth.webauthn_removed', 'Removed a biometric/face login credential.');

        return response()->json(['ok' => true]);
    }
}
