<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use App\Models\ActivityLog;
use App\Models\Signature;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConfidentialityController extends Controller
{
    public function acknowledge(Request $request): RedirectResponse
    {
        $request->validate([
            'agree' => 'accepted',
            'signature_data' => ['required', 'string'],
        ], [
            'agree.accepted' => 'Please check the acknowledgement box to continue.',
            'signature_data.required' => 'Please provide your electronic signature to continue.',
        ]);

        $png = $this->decodeSignature((string) $request->input('signature_data'));

        $user = $request->user();
        $path = 'signatures/'.$user->id.'-'.now()->format('YmdHis').'-'.bin2hex(random_bytes(4)).'.png';
        Storage::disk('supabase')->put($path, $png, 'private');

        $user->update([
            'confidentiality_acknowledged_at' => now(),
            'confidentiality_ack_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);

        Signature::create([
            'user_id' => $user->id,
            'role' => $user->role,
            'policy_version' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
            'signed_at' => now(),
            'signature_path' => $path,
        ]);

        ActivityLog::record($user, 'admin.confidentiality_acknowledged', 'Acknowledged the confidentiality policy v'.EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION.' with signature.');

        return redirect()->route('terms');
    }

    public function policy(): View
    {
        return view('admin.confidentiality-policy-print', [
            'policyVersion' => EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION,
        ]);
    }

    public function signatureImage(Request $request, Signature $signature)
    {
        abort_unless($signature->user_id === $request->user()->id, 403);

        if (! $signature->hasImage()) {
            abort(404);
        }

        $temporaryUrl = Storage::disk('supabase')->temporaryUrl($signature->signature_path, now()->addMinutes(30));

        return redirect($temporaryUrl)->withHeaders([
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    private function decodeSignature(string $dataUrl): string
    {
        $prefix = 'data:image/png;base64,';

        if (! str_starts_with($dataUrl, $prefix)) {
            throw ValidationException::withMessages([
                'signature_data' => 'The signature must be a PNG image.',
            ]);
        }

        $png = base64_decode(substr($dataUrl, strlen($prefix)), true);

        if ($png === false || $png === '') {
            throw ValidationException::withMessages([
                'signature_data' => 'The signature could not be read.',
            ]);
        }

        if (strlen($png) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'signature_data' => 'The signature image is too large.',
            ]);
        }

        if (strncmp($png, "\x89PNG\r\n\x1a\n", 8) !== 0) {
            throw ValidationException::withMessages([
                'signature_data' => 'The signature file is not a valid image.',
            ]);
        }

        return $png;
    }
}