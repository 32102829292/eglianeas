@extends('layouts.auth')

@section('title', 'Sign Up — Egliane Accounting Services')

@php
    $regSteps = ['Account', 'Business', 'Contact', 'Tax Details', 'Verify'];
    $regStartStep = 0;
    if ($errors->hasAny(['business_type', 'line_of_business', 'line_of_business_other', 'bir_registration_type'])) {
        $regStartStep = 1;
    } elseif ($errors->hasAny(['business_address', 'contact_no', 'second_contact_name', 'second_contact_no', 'second_email'])) {
        $regStartStep = 2;
    } elseif ($errors->hasAny(['birth_date', 'tin_no', 'mother_maiden_name', 'father_name'])) {
        $regStartStep = 3;
    }
@endphp

@section('content')
    <h1 class="auth-title">Create your account</h1>
    <p class="auth-sub">Free to sign up. Bookkeeping, tax filing and document storage &mdash; all in one place.</p>

    <div class="register-steps">
        @foreach ($regSteps as $i => $label)
            <div class="register-step {{ $i === $regStartStep ? 'active' : '' }}" data-step-marker="{{ $i }}">
                <span>{{ $i + 1 }}</span>
                <small>{{ $label }}</small>
            </div>
        @endforeach
    </div>

    <form method="POST" action="{{ route('register') }}" id="registerForm" novalidate>
        @csrf

        {{-- Step 1: Account basics --}}
        <div class="reg-step-panel {{ $regStartStep === 0 ? 'active' : '' }}" data-step="0">
            <div class="form-group">
                <label class="form-label" for="name">Full name</label>
                <input class="form-control" id="name" type="text" name="name" value="{{ old('name') }}" required autocomplete="name" placeholder="Juan Dela Cruz">
                @error('name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="email">Email</label>
                <input class="form-control" id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="email" placeholder="you@gmail.com"
                    data-check-url="{{ route('register.checkEmail') }}" data-resume-url="{{ route('register.resumeVerify') }}">
                <p class="form-hint">Use your Gmail address &mdash; your verification code and filing notices will be sent here.</p>
                @error('email')<div class="form-error">{{ $message }}</div>@enderror
                <div class="form-error" id="emailTakenBox" hidden>This email is already registered. Please <a href="{{ route('login') }}">log in</a> instead.</div>
                <div class="form-note" id="emailUnverifiedBox" hidden>
                    You already started signing up with this email.
                    <button type="button" class="btn btn-outline btn-sm mt-1" id="resumeVerifyBtn">Continue to verify it instead</button>
                </div>
                @if (session('email_registered'))
                    <div class="form-error">This email is already registered. Please <a href="{{ route('login') }}">log in</a> instead.</div>
                @endif
            </div>

            <div class="pin-panel">
                <input type="hidden" name="pin" id="pin">
                <input type="hidden" name="pin_confirmation" id="pin_confirmation">
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

            <button type="button" class="btn btn-primary btn-block mt-2" id="regStep1Next" data-next="1">Continue</button>
        </div>

        {{-- Step 2: Business info --}}
        <div class="reg-step-panel {{ $regStartStep === 1 ? 'active' : '' }}" data-step="1">
            <div class="form-group">
                <label class="form-label" for="business_name">Business name</label>
                <input class="form-control" id="business_name" type="text" name="business_name" value="{{ old('business_name') }}" required placeholder="Acme Trading Co.">
                @error('business_name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="business_type">Business type</label>
                <select class="form-control" id="business_type" name="business_type" required>
                    <option value="" disabled {{ old('business_type') ? '' : 'selected' }}>Select business type</option>
                    @foreach ($businessTypes as $type)
                        <option value="{{ $type }}" {{ old('business_type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                    @endforeach
                </select>
                @error('business_type')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="line_of_business">Line of business</label>
                <select class="form-control" id="line_of_business" name="line_of_business" required data-lob-select>
                    <option value="" disabled {{ old('line_of_business') ? '' : 'selected' }}>Select line of business</option>
                    @foreach ($lineOfBusinessOptions as $lob)
                        <option value="{{ $lob }}" {{ old('line_of_business') === $lob ? 'selected' : '' }}>{{ $lob }}</option>
                    @endforeach
                </select>
                @error('line_of_business')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group" id="lobOtherGroup" {{ old('line_of_business') === 'Other' ? '' : 'hidden' }}>
                <label class="form-label" for="line_of_business_other">Describe your line of business</label>
                <input class="form-control" id="line_of_business_other" type="text" name="line_of_business_other" value="{{ old('line_of_business_other') }}" placeholder="e.g. Sari-sari store supplies">
                @error('line_of_business_other')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="bir_registration_type">BIR registration type</label>
                <select class="form-control" id="bir_registration_type" name="bir_registration_type" required>
                    <option value="" disabled {{ old('bir_registration_type') ? '' : 'selected' }}>Select BIR registration type</option>
                    @foreach ($birRegistrationTypes as $birType)
                        <option value="{{ $birType }}" {{ old('bir_registration_type') === $birType ? 'selected' : '' }}>{{ $birType }}</option>
                    @endforeach
                </select>
                @error('bir_registration_type')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="btn-group-row">
                <button type="button" class="btn btn-outline" data-back="0">Back</button>
                <button type="button" class="btn btn-primary" data-next="2">Continue</button>
            </div>
        </div>

        {{-- Step 3: Contact & address --}}
        <div class="reg-step-panel {{ $regStartStep === 2 ? 'active' : '' }}" data-step="2">
            <div class="form-group">
                <label class="form-label" for="business_address">Business address</label>
                <input class="form-control" id="business_address" type="text" name="business_address" value="{{ old('business_address') }}" required autocomplete="street-address" placeholder="123 Rizal Ave., Poblacion, Makati City">
                @error('business_address')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="contact_no">Contact no.</label>
                <input class="form-control" id="contact_no" type="tel" name="contact_no" value="{{ old('contact_no') }}" required autocomplete="tel" placeholder="0917 123 4567"
                    pattern="(?:\+63|0)[\d\s\-()]{7,17}" title="PH mobile or landline, e.g. 0917 123 4567">
                @error('contact_no')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="second_contact_name">2nd contact name</label>
                <input class="form-control" id="second_contact_name" type="text" name="second_contact_name" value="{{ old('second_contact_name') }}" required placeholder="Maria Dela Cruz">
                @error('second_contact_name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="second_contact_no">2nd contact no.</label>
                <input class="form-control" id="second_contact_no" type="tel" name="second_contact_no" value="{{ old('second_contact_no') }}" placeholder="0918 765 4321" required
                    pattern="(?:\+63|0)[\d\s\-()]{7,17}" title="PH mobile or landline, e.g. 0918 765 4321">
                @error('second_contact_no')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="second_email">2nd email</label>
                <input class="form-control" id="second_email" type="email" name="second_email" value="{{ old('second_email') }}" required placeholder="maria@example.com">
                @error('second_email')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="btn-group-row">
                <button type="button" class="btn btn-outline" data-back="1">Back</button>
                <button type="button" class="btn btn-primary" data-next="3">Continue</button>
            </div>
        </div>

        {{-- Step 4: Personal / tax info --}}
        <div class="reg-step-panel {{ $regStartStep === 3 ? 'active' : '' }}" data-step="3">
            <p class="form-hint">Used for your BIR registrations and filings.</p>
            <div class="form-group">
                <label class="form-label" for="birth_date">Birth date</label>
                <input class="form-control" id="birth_date" type="date" name="birth_date" value="{{ old('birth_date') }}" required
                    min="1900-01-02" max="{{ now()->subDay()->toDateString() }}">
                @error('birth_date')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="tin_no">TIN no.</label>
                <input class="form-control" id="tin_no" type="text" name="tin_no" value="{{ old('tin_no') }}" placeholder="123-456-789"
                    pattern="\d{3}-?\d{3}-?\d{3}(-?\d{3})?" title="e.g. 123-456-789 or 123-456-789-000" required>
                @error('tin_no')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="mother_maiden_name">Mother&rsquo;s maiden name</label>
                <input class="form-control" id="mother_maiden_name" type="text" name="mother_maiden_name" value="{{ old('mother_maiden_name') }}" placeholder="Juanita Santos" required>
                @error('mother_maiden_name')<div class="form-error">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label" for="father_name">Father&rsquo;s name</label>
                <input class="form-control" id="father_name" type="text" name="father_name" value="{{ old('father_name') }}" placeholder="Pedro Dela Cruz" required>
                @error('father_name')<div class="form-error">{{ $message }}</div>@enderror
            </div>

            <div class="btn-group-row">
                <button type="button" class="btn btn-outline" data-back="2">Back</button>
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
