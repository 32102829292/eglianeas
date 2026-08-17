<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PinLoginController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email'],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
        ]);

        $user = User::query()->where('email', $request->email)->first();

        if ($user === null || ! $user->hasPin() || ! Hash::check($request->pin, $user->pin)) {
            throw ValidationException::withMessages([
                'pin' => 'Invalid PIN for this account.',
            ]);
        }

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages([
                'pin' => 'Please verify your email before logging in.',
            ]);
        }

        Auth::login($user);

        $request->session()->regenerate();

        ActivityLog::record($user, 'auth.login_pin', 'Logged in using PIN.');

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'name' => $user->name,
                'email' => $user->email,
                'redirect' => $user->getDashboardRoute(),
            ]);
        }

        return redirect()->intended($user->getDashboardRoute());
    }
}
