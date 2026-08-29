<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Support\WebauthnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class WebauthnLoginController extends Controller
{
    public function options(Request $request, WebauthnService $webauthn): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
        ]);

        $user = User::query()->where('email', $request->email)->first();

        if ($user === null || ! $user->hasWebauthnCredentials()) {
            return response()->json(['error' => 'Face ID isn\'t set up yet. Log in with your PIN, then set up Face ID from Security Settings.'], 422);
        }

        $options = $webauthn->requestOptionsForBrowser($user);

        session(['webauthn.login_email' => $user->email]);

        return response()->json($options);
    }

    public function verify(Request $request, WebauthnService $webauthn): RedirectResponse|JsonResponse
    {
        $request->validate([
            'credential' => ['required', 'array'],
        ]);

        $email = $request->session()->get('webauthn.login_email');
        $user = $email ? User::query()->where('email', $email)->first() : null;

        if ($user === null) {
            return response()->json(['error' => 'Session expired. Please try again.'], 422);
        }

        $rawId = base64_decode(strtr((string) $request->input('credential.rawId'), '-_', '+/'), true);

        $credential = $user->webauthnCredentials->first(function ($item) use ($rawId, $webauthn) {
            $record = $webauthn->recordFromCredential($item);

            return $rawId !== false && hash_equals($record->publicKeyCredentialId, $rawId);
        });

        if ($credential === null) {
            return response()->json(['error' => 'Face ID not recognized on this device. Try your PIN instead, or set up Face ID from Security Settings on this device.'], 422);
        }

        try {
            $newCounter = $webauthn->verifyRequest($request->input('credential'), $credential, (string) $user->id);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Biometric verification failed. Please try again or use your PIN.'], 422);
        }

        $recordData = $credential->record;
        $recordData['counter'] = $newCounter;
        $credential->update(['record' => $recordData, 'last_used_at' => now()]);

        $request->session()->forget('webauthn.login_email');

        Auth::login($user);
        $request->session()->regenerate();

        ActivityLog::record($user, 'auth.login_face', 'Logged in using biometric/face recognition.');

        return response()->json([
            'name' => $user->name,
            'email' => $user->email,
            'redirect' => $user->getDashboardRoute(),
        ]);
    }
}
