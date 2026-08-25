<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $impersonatorId = $request->session()->get('impersonator_id');

        if ($impersonatorId) {
            $client = Auth::user();
            $admin = \App\Models\User::find($impersonatorId);

            if ($admin && ! $admin->trashed()) {
                Auth::login($admin);
                $request->session()->forget('impersonator_id');

                \App\Models\ActivityLog::record($admin, 'admin.impersonate_stop', "Admin {$admin->name} ended impersonation session with client {$client->name} ({$client->email}) via logout.");

                return redirect()->route('admin.dashboard')->with('status', 'Impersonation ended. You are now viewing as yourself again.');
            }

            $request->session()->forget('impersonator_id');
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
