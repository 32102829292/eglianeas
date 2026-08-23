<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\VerificationCodeMail;
use App\Models\ClientProfile;
use App\Models\User;
use App\Models\VerificationCode;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'businessTypes' => ClientProfile::BUSINESS_TYPES,
            'lineOfBusinessOptions' => ClientProfile::LINE_OF_BUSINESS_OPTIONS,
            'birRegistrationTypes' => ClientProfile::BIR_REGISTRATION_TYPES,
        ]);
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
                'regex:/@gmail\.com$/i',
            ],
            'pin' => ['required', 'string', 'regex:/^\d{4}$/'],
            'pin_confirmation' => ['required', 'same:pin'],
            'terms' => ['accepted'],
            'business_type' => ['required', 'string', Rule::in(ClientProfile::BUSINESS_TYPES)],
            'line_of_business' => ['required', 'string', Rule::in(ClientProfile::LINE_OF_BUSINESS_OPTIONS)],
            'line_of_business_other' => ['nullable', 'required_if:line_of_business,Other', 'string', 'max:255'],
            'bir_registration_type' => ['required', 'string', Rule::in(ClientProfile::BIR_REGISTRATION_TYPES)],
            'business_address' => ['required', 'string', 'max:500'],
            'contact_no' => ['required', 'string', 'max:40'],
            'second_contact_name' => ['nullable', 'string', 'max:255'],
            'second_contact_no' => ['nullable', 'string', 'max:40'],
            'second_email' => ['nullable', 'email', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'tin_no' => ['nullable', 'string', 'max:40'],
            'mother_maiden_name' => ['nullable', 'string', 'max:255'],
            'father_name' => ['nullable', 'string', 'max:255'],
        ], [
            'email.regex' => 'Please use a valid Gmail address (e.g. you@gmail.com).',
            'pin.regex' => 'Your PIN must be exactly 4 digits.',
            'pin_confirmation.same' => 'The PIN confirmation does not match.',
            'terms.accepted' => 'You must agree to the terms and conditions.',
            'business_type.required' => 'Please select your business type.',
            'line_of_business.required' => 'Please select your line of business.',
            'line_of_business_other.required_if' => 'Please describe your line of business.',
            'bir_registration_type.required' => 'Please select your BIR registration type.',
            'business_address.required' => 'Please enter your business address.',
            'contact_no.required' => 'Please enter your contact number.',
            'birth_date.before' => 'Birth date must be in the past.',
            'second_email.email' => 'Please enter a valid 2nd email address.',
        ]);

        $check = $this->classifyEmail($request->input('email'));

        if ($check['status'] === 'verified') {
            return back()->withInput()->with('email_registered', true);
        }

        if ($check['status'] === 'unverified') {
            $this->startVerificationSession($check['user']);

            return redirect()
                ->route('verify.account')
                ->with('status', 'You already started signing up with '.$check['user']->email." but haven't verified it yet. Enter your code below, or request a new one.");
        }

        $user = User::create([
            'name' => $request->name,
            'business_name' => $request->business_name,
            'email' => $request->email,
            'password' => Hash::make(Str::random(64)),
            'pin' => Hash::make($request->pin),
            'pin_set_at' => now(),
            'role' => User::ROLE_CLIENT,
        ]);

        $lineOfBusiness = $request->string('line_of_business')->toString() === 'Other'
            ? ($request->string('line_of_business_other')->toString() ?: null)
            : $request->string('line_of_business')->toString();

        ClientProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'business_type' => $request->business_type,
                'line_of_business' => $lineOfBusiness,
                'bir_registration_type' => $request->bir_registration_type,
                'business_address' => $request->business_address,
                'contact_no' => $request->contact_no,
                'second_contact_name' => $request->second_contact_name,
                'second_contact_no' => $request->second_contact_no,
                'second_email' => $request->second_email,
                'birth_date' => $request->birth_date,
                'tin_no' => $request->tin_no,
                'mother_maiden_name' => $request->mother_maiden_name,
                'father_name' => $request->father_name,
            ]
        );

        $this->sendCode($user);

        session([
            'verification_user_id' => $user->id,
            'verification_sent_at' => now()->getTimestamp(),
        ]);
        session(['setup_face' => $request->boolean('setup_face')]);

        ActivityLog::record($user, 'account.created', 'New client account created.');

        return redirect()->route('verify.account');
    }

    public function checkEmail(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'regex:/@gmail\.com$/i'],
        ], [
            'email.regex' => 'Please use a valid Gmail address (e.g. you@gmail.com).',
        ]);

        return response()->json(['status' => $this->classifyEmail($request->input('email'))['status']]);
    }

    public function resumeVerify(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'regex:/@gmail\.com$/i'],
        ], [
            'email.regex' => 'Please use a valid Gmail address (e.g. you@gmail.com).',
        ]);

        $check = $this->classifyEmail($request->input('email'));

        if ($check['status'] !== 'unverified') {
            return response()->json(['message' => 'This email cannot be resumed as an unverified signup.'], 409);
        }

        $this->startVerificationSession($check['user']);

        return response()->json(['redirect' => route('verify.account')]);
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

    private function classifyEmail(string $email): array
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            return ['status' => 'available'];
        }

        return [
            'status' => $user->hasVerifiedEmail() ? 'verified' : 'unverified',
            'user' => $user,
        ];
    }

    private function startVerificationSession(User $user): void
    {
        session(['verification_user_id' => $user->id]);

        $this->seedCooldownFromLastCode($user);
    }

    private function seedCooldownFromLastCode(User $user): void
    {
        $last = VerificationCode::query()->where('user_id', $user->id)->latest()->first();

        if ($last !== null && $last->created_at->getTimestamp() > now()->getTimestamp() - 60) {
            session(['verification_sent_at' => $last->created_at->getTimestamp()]);
        }
    }

    private function sendCode(User $user): void
    {
        $code = (string) random_int(100000, 999999);

        VerificationCode::issue($user, $code);

        Mail::to($user->email)->send(new VerificationCodeMail($code, $user->name));
    }
}
