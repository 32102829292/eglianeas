<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureAdminConfidentialityAcknowledged;
use Illuminate\View\View;

class TermsController extends Controller
{
    public function show(): View
    {
        $user = auth()->user();
        $version = EnsureAdminConfidentialityAcknowledged::CURRENT_VERSION;

        $signature = null;
        if ($user) {
            $signature = $user->signatures()
                ->where('policy_version', $version)
                ->orderByDesc('signed_at')
                ->first();
        }

        return view('terms', [
            'signature' => $signature,
            'policyVersion' => $version,
        ]);
    }
}