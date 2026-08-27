{{-- Reusable login form — used on the landing page and the /login page. --}}

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

<div id="savedAccounts" class="saved-accounts" hidden></div>
<p class="saved-accounts-alt" id="savedAccountsAlt" hidden>
    <button type="button" class="link-style" id="diffAccountLink">+ Log in with a different account</button>
</p>

<div class="form-group" id="emailGroup">
    <label class="form-label" for="authEmail">Gmail</label>
    <input class="form-control" id="authEmail" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" placeholder="you@gmail.com">
    @error('email')<div class="form-error">{{ $message }}</div>@enderror
</div>

<div class="remember-row" id="rememberRow">
    <label class="checkbox-row remember-check">
        <input type="checkbox" id="rememberEmail">
        <span>Remember me on this device</span>
    </label>
    <a href="#" id="notYouLink" class="not-you" hidden>Not you? Use a different account</a>
</div>

<div class="auth-tabs" role="tablist">
    <button type="button" class="auth-tab active" data-tab="pin" role="tab">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="8" width="20" height="12" rx="2"/><path d="M12 12v4M6 12v4M18 12v4"/></svg>
        PIN
    </button>
    <button type="button" class="auth-tab" data-tab="face" role="tab">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 15v3a4 4 0 0 0 4 4h3"/><path d="M22 15v3a4 4 0 0 1-4 4h-3"/><path d="M2 9V6a4 4 0 0 1 4-4h3"/><path d="M22 9V6a4 4 0 0 0-4-4h-3"/><circle cx="12" cy="12" r="3"/></svg>
        Face
    </button>
</div>

{{-- PIN login --}}
<div class="auth-panel active" data-panel="pin" role="tabpanel">
    <form method="POST" action="{{ route('login.pin') }}" id="pinForm" novalidate>
        @csrf
        <input type="hidden" name="email" id="pinEmail">
        <input type="hidden" name="pin" id="pinValue">
        <div class="pin-panel">
            <div class="pin-display">
                @for ($i = 0; $i < 4; $i++)<span class="pin-dot"></span>@endfor
            </div>
            <div class="keypad" id="keypad">
                @foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9] as $n)
                    <button type="button" class="key" data-key="{{ $n }}">{{ $n }}</button>
                @endforeach
                <span></span>
                <button type="button" class="key" data-key="0">0</button>
                <button type="button" class="key backspace" data-key="back" aria-label="Delete">
                    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 4H8l-7 8 7 8h13a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2z"/><path d="m18 9-6 6M12 9l6 6"/></svg>
                </button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary btn-block" id="pinSubmitBtn">Log In</button>
        <p class="form-hint text-center">Enter your 4-digit PIN. First time? Set up a PIN under Security settings.</p>
        <p class="text-center mt-1"><a href="{{ route('client.password.forgot') }}" class="link-style">Forgot your password?</a></p>
        <div class="form-error text-center" id="pinError" hidden></div>
        @error('pin')<div class="form-error text-center">{{ $message }}</div>@enderror
        <p class="text-center mt-1" id="verifyResendRow" hidden>
            <button type="button" class="link-style" id="goVerifyBtn">Resend verification code</button>
        </p>
    </form>
</div>

{{-- Face login --}}
<div class="auth-panel" data-panel="face" role="tabpanel">
    <div class="face-error" id="faceError"></div>
    <div class="face-box">
        <div class="face-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 15v3a4 4 0 0 0 4 4h3"/><path d="M22 15v3a4 4 0 0 1-4 4h-3"/><path d="M2 9V6a4 4 0 0 1 4-4h3"/><path d="M22 9V6a4 4 0 0 0-4-4h-3"/><circle cx="12" cy="12" r="3"/></svg>
        </div>
        <button type="button" class="btn btn-primary btn-block" id="faceLoginBtn">Continue with face</button>
        <p class="face-hint">Uses your device&rsquo;s Face ID / Windows Hello. Your face data never leaves your device.</p>
    </div>
</div>

<p class="auth-footer auth-switch-row">
    <button type="button" class="link-style" id="useFaceLink">Use Face ID instead</button>
</p>
<p class="auth-footer">Don&rsquo;t have an account? <a href="{{ route('register') }}">Sign Up</a></p>
