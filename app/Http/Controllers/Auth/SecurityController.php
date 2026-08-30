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
        if (session('impersonator_id')) {
            return redirect()->intended(route('admin.dashboard'))
                ->with('status', 'Security settings aren\'t available while impersonating a client.');
        }

        return view('settings.security', [
            'user' => Auth::user(),
            'credentials' => Auth::user()->webauthnCredentials()->latest()->get(),
        ]);
    }

    public function setPin(Request $request): RedirectResponse
    {
        abort_if(session('impersonator_id'), 403, 'PIN changes are not available while viewing as another user.');

        $rules = [
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ];

        $messages = [
            'pin.regex' => 'Your PIN must be exactly 4 digits.',
            'pin_confirmation.same' => 'The PIN confirmation does not match.',
        ];

        $user = Auth::user();

        if ($user->password !== null) {
            $rules['current_password'] = ['required', 'current_password'];
        } elseif ($user->pin !== null) {
            $rules['current_password'] = ['required', 'string', 'regex:/^\d{4}$/'];
            $messages['current_password.regex'] = 'Enter your current 4-digit PIN.';
        }

        $request->validate($rules, $messages);

        if ($user->password === null && $user->pin !== null) {
            abort_unless(Hash::check($request->current_password, $user->pin), 403, 'Your current PIN does not match.');
        }

        $user->forceFill([
            'pin' => Hash::make($request->pin),
            'pin_set_at' => now(),
        ])->save();

        ActivityLog::record($user, 'security.pin_set', 'Security PIN was set or updated.');

        return back()->with('status', 'Your PIN login has been saved. Use it to log in quickly.');
    }
}
