<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use App\Models\ActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                'unique:users,email',
                'regex:/@gmail\.com$/i',
            ],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
            'terms' => ['accepted'],
        ], [
            'email.regex' => 'Please use a valid Gmail address (e.g. you@gmail.com).',
            'email.unique' => 'An account with this Gmail address already exists. Please log in instead.',
            'pin.regex' => 'Your PIN must be exactly 4 digits.',
            'pin_confirmation.same' => 'The PIN confirmation does not match.',
            'terms.accepted' => 'You must agree to the terms and conditions.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'business_name' => $request->business_name,
            'email' => $request->email,
            'password' => Hash::make(Str::random(64)),
            'pin' => Hash::make($request->pin),
            'pin_set_at' => now(),
            'role' => User::ROLE_CLIENT,
        ]);

        $this->sendCode($user);

        session([
            'verification_user_id' => $user->id,
            'verification_sent_at' => now()->getTimestamp(),
        ]);
        session(['setup_face' => $request->boolean('setup_face')]);

        ActivityLog::record($user, 'account.created', 'New client account created.');

        return redirect()->route('verify.account');
    }

    public function resendCode(Request $request): RedirectResponse
    {
        $userId = (int) $request->session()->get('verification_user_id');

        $user = $userId ? User::find($userId) : null;

        if ($user === null) {
            return redirect()->route('register');
        }

        $lastSentAt = $request->session()->get('verification_sent_at');
        $cooldown = 60;

        if ($lastSentAt !== null && (now()->getTimestamp() - (int) $lastSentAt) < $cooldown) {
            $remaining = $cooldown - (now()->getTimestamp() - (int) $lastSentAt);

            throw ValidationException::withMessages([
                'code' => "Please wait {$remaining} second(s) before requesting a new code.",
            ]);
        }

        $this->sendCode($user);
        $request->session()->put('verification_sent_at', now()->getTimestamp());

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    public function verifyForm(): View
    {
        $userId = (int) session('verification_user_id');

        $user = $userId ? User::find($userId) : null;

        if ($user === null) {
            return view('auth.verify-account', ['noPending' => true]);
        }

        if ($user->hasVerifiedEmail()) {
            return view('auth.verify-account', ['alreadyVerified' => true, 'noPending' => false]);
        }

        $latest = VerificationCode::query()
            ->where('user_id', $user->id)
            ->latest()
            ->first();

        return view('auth.verify-account', [
            'email' => $user->email,
            'noPending' => false,
            'alreadyVerified' => false,
            'devCode' => $latest?->code_plain,
            'cooldownUntil' => session('verification_sent_at'),
        ]);
    }

    public function verifyStore(Request $request): RedirectResponse
    {
        $userId = (int) $request->session()->get('verification_user_id');

        $user = $userId ? User::find($userId) : null;

        if ($user === null) {
            return redirect()->route('register');
        }

        if ($user->hasVerifiedEmail()) {
            $request->session()->forget('verification_user_id');

            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $record = VerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if ($record === null) {
            throw ValidationException::withMessages([
                'code' => 'No active verification code was found. Please request a new one.',
            ]);
        }

        if ($record->attempts >= 5) {
            throw ValidationException::withMessages([
                'code' => 'Too many failed attempts. Please request a new code.',
            ]);
        }

        if ($record->isExpired() || ! $record->matches($request->code)) {
            $record->increment('attempts');

            throw ValidationException::withMessages([
                'code' => 'That code is incorrect or has expired. Please try again.',
            ]);
        }

        $record->update(['used_at' => now()]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $request->session()->forget('verification_user_id');

        Auth::login($user);

        $request->session()->regenerate();

        ActivityLog::record($user, 'account.verified', 'Email address verified.');

        if ($request->session()->pull('setup_face')) {
            return redirect()->route('security.index')->with('status', 'Welcome, '.$user->name.'! Set up face recognition now so you can log in even faster.');
        }

        return redirect()->intended($user->getDashboardRoute());
    }

    private function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        VerificationCode::issue($user, $code);

        Mail::to($user->email)->send(new VerificationCodeMail($code, $user->name));
    }
}
