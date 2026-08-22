@extends('layouts.auth')

@section('title', 'Sign Up — Egliane Accounting Services')

@section('content')
    <h1 class="auth-title">Create your account</h1>
    <p class="auth-sub">Free to sign up. Bookkeeping, tax filing and document storage — all in one place.</p>

@php $regStartStep = (old('name') && session('email_registered')) ? 1 : 0; @endphp

<div class="register-steps">
    @php $regSteps = ['Details', 'Email', 'Security']; @endphp
    @foreach ($regSteps as $i => $label)
        <div class="register-step {{ $i === $regStartStep ? 'active' : '' }}" data-step-marker="{{ $i }}">
            <span>{{ $i + 1 }}</span>
            <small>{{ $label }}</small>
        </div>
    @endforeach
</div>

    <form method="POST" action="{{ route('register') }}" id="registerForm" novalidate>
        @csrf

        {{-- Step 1: name + business --}}
        <div class="reg-step-panel {{ $regStartStep === 0 ? 'active' : '' }}" data-step="0">
            <div class="form-group">
                <label class="form-label" for="name">Full name</label>
                <input class="form-control" id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Juan Dela Cruz">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="business_name">Business name</label>
                <input class="form-control" id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" required placeholder="Acme Trading Co.">
                @error('business_name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <button type="button" class="btn btn-primary btn-block mt-2" data-next="1">Continue</button>
        </div>

        {{-- Step 2: email --}}
        <div class="reg-step-panel {{ $regStartStep === 1 ? 'active' : '' }}" data-step="1">
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@gmail.com">
                <p class="form-hint">Use your Gmail address — your verification code and filing notices will be sent here.</p>
                @error('email')<div class="form-error">{{ $message }}</div>@enderror
                @if (session('email_registered'))
                    <div class="form-error">This email is already registered. Please <a href="{{ route('login') }}">log in</a> instead.</div>
                @endif
            </div>
            <div class="btn-group-row">
                <button type="button" class="btn btn-outline" data-back="0">Back</button>
                <button type="button" class="btn btn-primary" data-next="2">Continue</button>
            </div>
        </div>

        {{-- Step 3: set PIN + optional face + terms --}}
        <div class="reg-step-panel" data-step="2">
            <input type="hidden" name="pin" id="pin">
            <input type="hidden" name="pin_confirmation" id="pin_confirmation">
            <div class="pin-panel">
                <p class="reg-phase-label" id="regPhaseLabel">Set your PIN</p>
                <div class="pin-display">
                    @for ($i = 0; $i < 4; $i++)<span class="pin-dot"></span>@endfor
                </div>
                <div class="keypad" id="regKeypad">
                    @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
                        <button type="button" class="key" data-key="{{ $n }}">{{ $n }}</button>
                    @endforeach
                    <span class="keypad-blank"></span>
                    <button type="button" class="key" data-key="0">0</button>
                    <button type="button" class="key backspace" data-key="back" aria-label="Delete">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/><path d="m18 9-6 6M12 9l6 6"/></svg>
                    </button>
                    <button type="button" class="key ok-key" data-key="ok" aria-label="Confirm" disabled>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                </div>
                <p class="form-hint text-center">Your PIN is 4 digits. You&rsquo;ll enter it every time you log in.</p>
            </div>
            <div class="form-error text-center" id="regPinError" hidden></div>
            @error('pin')<div class="form-error text-center">{{ $message }}</div>@enderror
            @error('pin_confirmation')<div class="form-error text-center">{{ $message }}</div>@enderror

            <div class="reg-checks">
                <label class="checkbox-row">
                    <input type="checkbox" name="setup_face" id="setup_face" value="1">
                    <span>Set up face recognition after verifying my email <em class="opt-tag">(optional)</em></span>
                </label>

                <label class="checkbox-row">
                    <input type="checkbox" name="terms" id="terms" value="1">
                    <span>I agree to the <a href="#" data-toggle="#termsModal">terms and conditions</a></span>
                </label>
                <div class="form-error" id="termsError" hidden></div>
            </div>

            <div class="btn-group-row">
                <button type="button" class="btn btn-outline" data-back="1">Back</button>
                <button type="button" class="btn btn-primary" id="submitReg">Create account</button>
            </div>
        </div>
    </form>

    <div id="termsModal" class="modal hidden">
        <div class="modal-card">
            <h3>Terms &amp; Confidentiality</h3>
            <p>By creating an account you agree to use this portal only for your own business records, to keep your login details private, and to submit accurate information. Documents you upload are reviewed by our accounting team.</p>
            <p>All client information, financial data, and documents accessible through this platform are strictly confidential. You agree not to disclose, share, screenshot, copy, or distribute any information obtained through this system to any party outside Egliane Accounting Services, without prior written authorization.</p>
            <p>Read the full <a href="{{ route('terms') }}" target="_blank">Terms &amp; Confidentiality</a>.</p>
            <button type="button" class="btn btn-primary btn-block" data-modal-close="#termsModal">I understand</button>
        </div>
    </div>

    <p class="auth-footer">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
@endsection
