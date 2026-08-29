<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ForgotPinController extends Controller
{
    public function showEmailForm(): View
    {
        return view('auth.forgot-pin');
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
        ]);

        $email = strtolower(trim($request->input('email')));

        if (RateLimiter::tooManyAttempts('forgot-pin:'.$email, 3)) {
            $seconds = RateLimiter::availableIn('forgot-pin:'.$email);

            throw ValidationException::withMessages([
                'email' => "Too many requests. Please try again in {$seconds} seconds.",
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            RateLimiter::hit('forgot-pin:'.$email, 300);

            $code = (string) random_int(100000, 999999);

            VerificationCode::issue($user, $code, 15);

            Mail::to($user->email)->send(new VerificationCodeMail($code, $user->name, 15));

            session([
                'forgot_pin_user_id' => $user->id,
                'forgot_pin_sent_at' => now()->getTimestamp(),
            ]);
        }

        session(['forgot_pin_email' => $email]);

        return redirect()->route('forgot-pin.verify')
            ->with('status', 'If an account exists with that email, a verification code has been sent.');
    }

    public function resendCode(Request $request): RedirectResponse
    {
        $userId = (int) session('forgot_pin_user_id');

        $user = $userId ? User::find($userId) : null;

        if ($user === null) {
            return redirect()->route('forgot-pin');
        }

        $lastSentAt = session('forgot_pin_sent_at');
        $cooldown = 60;

        if ($lastSentAt !== null && (now()->getTimestamp() - (int) $lastSentAt) < $cooldown) {
            $remaining = $cooldown - (now()->getTimestamp() - (int) $lastSentAt);

            throw ValidationException::withMessages([
                'code' => "Please wait {$remaining} second(s) before requesting a new code.",
            ]);
        }

        $code = (string) random_int(100000, 999999);

        VerificationCode::issue($user, $code, 15);

        Mail::to($user->email)->send(new VerificationCodeMail($code, $user->name, 15));

        session(['forgot_pin_sent_at' => now()->getTimestamp()]);

        return back()->with('status', 'A new verification code has been sent to your email.');
    }

    public function showVerifyForm(): View|RedirectResponse
    {
        if (! session('forgot_pin_email')) {
            return redirect()->route('forgot-pin');
        }

        $email = session('forgot_pin_email');

        $user = session('forgot_pin_user_id') ? User::find((int) session('forgot_pin_user_id')) : null;

        return view('auth.forgot-pin-verify', [
            'email' => $email,
            'maskedEmail' => $this->maskEmail($email),
            'devCode' => $user
                ? VerificationCode::query()->where('user_id', $user->id)->latest()->value('code_plain')
                : null,
            'cooldownUntil' => session('forgot_pin_sent_at'),
        ]);
    }

    public function verifyCode(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'code' => ['required', 'string', 'size:6'],
        ]);

        $email = strtolower(trim($request->input('email')));

        $user = session('forgot_pin_user_id') ? User::find((int) session('forgot_pin_user_id')) : null;

        if ($user === null || $user->email !== $email) {
            return back()->withErrors(['code' => 'Invalid code.'])->withInput();
        }

        $record = VerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->latest()
            ->first();

        if ($record === null) {
            return back()->withErrors(['code' => 'No active verification code was found. Please request a new one.']);
        }

        if ($record->attempts >= 5) {
            return back()->withErrors(['code' => 'Too many failed attempts. Please request a new code.']);
        }

        if ($record->isExpired() || ! $record->matches($request->code)) {
            $record->increment('attempts');

            return back()->withErrors(['code' => 'That code is incorrect or has expired. Please try again.']);
        }

        $record->update(['used_at' => now()]);

        return redirect()->route('forgot-pin.reset')->with('status', 'Code verified. Now set your new PIN.');
    }

    public function showResetForm(): View|RedirectResponse
    {
        if (! session('forgot_pin_user_id')) {
            return redirect()->route('forgot-pin');
        }

        return view('auth.forgot-pin-reset');
    }

    public function resetPin(Request $request): RedirectResponse
    {
        $request->validate([
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
        ], [
            'pin.regex' => 'Your PIN must be exactly 4 digits.',
            'pin_confirmation.same' => 'Your PIN confirmation does not match.',
        ]);

        $user = session('forgot_pin_user_id') ? User::find((int) session('forgot_pin_user_id')) : null;

        if ($user === null) {
            return redirect()->route('forgot-pin');
        }

        $recentlyUsedCode = VerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNotNull('used_at')
            ->latest()
            ->first();

        if ($recentlyUsedCode === null || $recentlyUsedCode->isExpired()) {
            return redirect()->route('forgot-pin')->with('status', 'Your PIN reset session has expired. Please start over.');
        }

        $user->forceFill([
            'pin' => Hash::make($request->pin),
            'pin_set_at' => now(),
        ])->save();

        VerificationCode::query()
            ->where('user_id', $user->id)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        ActivityLog::record($user, 'auth.pin_reset', 'PIN was reset via email verification.');

        $request->session()->forget(['forgot_pin_user_id', 'forgot_pin_sent_at', 'forgot_pin_email']);

        return redirect()->route('login')->with('status', 'Your PIN has been reset. You can now log in with your new PIN.');
    }

    private function maskEmail(string $email): string
    {
        [$local, $domain] = explode('@', $email, 2);

        $visible = mb_substr($local, 0, 2);

        $masked = $visible.str_repeat('*', max(3, mb_strlen($local) - 2));

        return $masked.'@'.$domain;
    }
}