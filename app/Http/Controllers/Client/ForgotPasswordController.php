<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function showEmailForm(): View
    {
        return view('client.auth.forgot-password');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $email = $request->input('email');

        if (RateLimiter::tooManyAttempts('password-reset:'.$email, 3)) {
            $seconds = RateLimiter::availableIn('password-reset:'.$email);

            return back()->withErrors([
                'email' => "Too many requests. Please try again in {$seconds} seconds.",
            ]);
        }

        $user = User::where('email', $email)->where('role', 'client')->first();

        if ($user) {
            RateLimiter::hit('password-reset:'.$email, 300);

            $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $verification = VerificationCode::issue($user, $code, 15);

            Mail::to($user->email)->send(new VerificationCodeMail(
                code: $code,
                name: $user->name,
                expiresInMinutes: 15,
            ));
        }

        return redirect()->route('client.password.verify')
            ->with('status', 'If an account exists with that email, a verification code has been sent.')
            ->with('email_for_reset', $email);
    }

    public function showVerifyForm(): View
    {
        return view('client.auth.verify-code');
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = $request->input('email');
        $code = $request->input('code');

        if (RateLimiter::tooManyAttempts('password-verify:'.$email, 5)) {
            $seconds = RateLimiter::availableIn('password-verify:'.$email);

            return back()->withErrors([
                'code' => "Too many failed attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $user = User::where('email', $email)->where('role', 'client')->first();

        if (! $user) {
            RateLimiter::hit('password-verify:'.$email, 900);

            return back()->withErrors(['code' => 'Invalid code.']);
        }

        $verification = VerificationCode::where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if (! $verification) {
            RateLimiter::hit('password-verify:'.$email, 900);

            return back()->withErrors(['code' => 'Invalid code.']);
        }

        if ($verification->isExpired()) {
            return back()->withErrors(['code' => 'This code has expired. Please request a new one.']);
        }

        if ($verification->attempts >= 5) {
            return back()->withErrors(['code' => 'Too many failed attempts for this code. Please request a new one.']);
        }

        if (! $verification->matches($code)) {
            RateLimiter::hit('password-verify:'.$email, 900);
            $verification->increment('attempts');

            return back()->withErrors(['code' => 'Invalid code.']);
        }

        RateLimiter::clear('password-verify:'.$email);
        $verification->update(['used_at' => now()]);

        return redirect()->route('client.password.reset')
            ->with('reset_email', $email)
            ->with('reset_token', $code);
    }

    public function showResetForm(): View
    {
        return view('client.auth.reset-password');
    }

    public function resetPassword(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $email = $request->input('email');
        $token = session('reset_token');

        if (! $token) {
            return redirect()->route('client.password.forgot')
                ->with('status', 'Session expired. Please start the password reset process again.');
        }

        $user = User::where('email', $email)->where('role', 'client')->first();

        if (! $user) {
            return back()->withErrors(['email' => 'No account found with this email.']);
        }

        $verification = VerificationCode::where('user_id', $user->id)
            ->where('code', $token)
            ->whereNotNull('used_at')
            ->latest()
            ->first();

        if (! $verification || ! $verification->matches($token)) {
            return redirect()->route('client.password.forgot')
                ->with('status', 'Session expired. Please start the password reset process again.');
        }

        $user->forceFill([
            'password' => Hash::make($request->password),
        ])->save();

        RateLimiter::clear('password-reset:'.$email);

        session()->forget(['reset_email', 'reset_token']);

        return redirect()->route('login')
            ->with('status', 'Your password has been reset. You can now log in.');
    }
}
