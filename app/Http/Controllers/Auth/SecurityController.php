<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class SecurityController extends Controller
{
    public function index(): \Illuminate\View\View
    {
        return view('settings.security', [
            'user' => Auth::user(),
            'credentials' => Auth::user()->webauthnCredentials()->latest()->get(),
        ]);
    }

    public function setPin(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ], [
            'pin.regex' => 'Your PIN must be exactly 4 digits.',
            'pin_confirmation.same' => 'The PIN confirmation does not match.',
        ]);

        $user = Auth::user();

        $user->forceFill([
            'pin' => Hash::make($request->pin),
            'pin_set_at' => now(),
        ])->save();

        ActivityLog::record($user, 'security.pin_set', 'Security PIN was set or updated.');

        return back()->with('status', 'Your PIN login has been saved. Use it to log in quickly.');
    }
}
