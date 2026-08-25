<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ImpersonateController extends Controller
{
    public function start(User $client): RedirectResponse
    {
        abort_unless(auth()->check(), 401);
        abort_unless(auth()->user()->isAdmin(), 403);
        abort_if($client->isAdmin(), 403, 'Cannot impersonate an admin account.');

        if (session('impersonator_id')) {
            return redirect()->route('admin.clients.show', $client)
                ->with('error', 'Already impersonating a client. Exit first before impersonating another.');
        }

        session(['impersonator_id' => auth()->id()]);
        Auth::login($client);

        $admin = User::find(session('impersonator_id'));
        ActivityLog::record($admin, 'admin.impersonate_start', "Admin {$admin->name} started viewing as client {$client->name} ({$client->email}).");

        return redirect()->route('client.dashboard');
    }

    public function stop(): RedirectResponse
    {
        $impersonatorId = session('impersonator_id');

        if (! $impersonatorId) {
            return redirect()->route('client.dashboard');
        }

        $client = Auth::user();
        $admin = User::find($impersonatorId);

        if (! $admin || $admin->trashed()) {
            session()->forget('impersonator_id');
            Auth::logout();
            return redirect()->route('login')->with('error', 'Original admin session is no longer available. Please log in again.');
        }

        Auth::login($admin);
        session()->forget('impersonator_id');

        ActivityLog::record($admin, 'admin.impersonate_stop', "Admin {$admin->name} ended impersonation session with client {$client->name} ({$client->email}).");

        return redirect()->route('admin.clients.show', $client)->with('status', "Impersonation ended. You are now viewing as yourself again.");
    }
}
