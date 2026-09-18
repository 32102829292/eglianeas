@extends('layouts.dashboard')

@section('title', 'Security — Egliane Accounting Services')

@section('content')
    <div class="settings-security">
        <div class="page-head">
            <span class="sec-eyebrow">Security center</span>
            <h1>Security settings</h1>
            <p>Add an extra layer of convenience: log in with your PIN or your face.</p>
        </div>

        <div class="security-status">
            <span class="status-chip">
                <span class="status-dot {{ $user->hasPin() ? 'on' : '' }}"></span>
                <span class="status-k">PIN login</span>
                <span class="status-v">{{ $user->hasPin() ? 'On' : 'Off' }}</span>
            </span>
            <span class="status-chip">
                <span class="status-dot {{ count($credentials) > 0 ? 'on' : '' }}"></span>
                <span class="status-k">Face / Biometric</span>
                <span class="status-v">
                    @if (count($credentials) === 0)
                        Off
                    @elseif (count($credentials) === 1)
                        1 device
                    @else
                        {{ count($credentials) }} devices
                    @endif
                </span>
            </span>
        </div>

        <div class="grid-2">
            {{-- PIN --}}
            <div class="card sec-card">
                <div class="sec-card-head">
                    <span class="sec-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                    </span>
                    <div class="sec-card-titles">
                        <h3 class="sec-card-title">PIN login</h3>
                        <p class="sec-card-desc">{{ $user->hasPin() ? 'Your PIN is set. Update it anytime.' : 'Set a 4-digit PIN to log in without typing your password.' }}</p>
                    </div>
                </div>

                <form method="POST" action="{{ route('security.pin') }}">
                    @csrf
                    <div class="form-group">
                        <label class="form-label" for="current_password">Current password or PIN</label>
                        <input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password">
                        <small class="form-hint">Required only if your account already has a password or PIN.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pin">PIN</label>
                        <input class="form-control" id="pin" type="password" name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder="4 digits">
                        <small class="form-hint">Keep it private — use it to log in quickly.</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="pin_confirmation">Confirm PIN</label>
                        <input class="form-control" id="pin_confirmation" type="password" name="pin_confirmation" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder="Repeat your PIN">
                    </div>
                    <div class="sec-actions">
                        <button type="submit" class="btn btn-primary">{{ $user->hasPin() ? 'Update PIN' : 'Set PIN' }}</button>
                    </div>
                </form>
            </div>

            {{-- Biometric --}}
            <div class="card sec-card">
                <div class="sec-card-head">
                    <span class="sec-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8.5 14.5c1 1.4 2.2 2 3.5 2s2.5-.6 3.5-2"/><line x1="9" y1="10" x2="9.01" y2="10"/><line x1="15" y1="10" x2="15.01" y2="10"/></svg>
                    </span>
                    <div class="sec-card-titles">
                        <h3 class="sec-card-title">Face / Biometric login</h3>
                        <p class="sec-card-desc">Use your device&rsquo;s Face ID, Windows Hello, fingerprint, or another supported biometric authenticator to sign in quickly. Face data stays on your device.</p>
                    </div>
                </div>

                <div class="bio-status" data-bio-status="{{ count($credentials) > 0 ? 'enabled' : 'not-enabled' }}">
                    <span class="bio-badge{{ count($credentials) > 0 ? ' online' : '' }}" id="bioBadge">
                        <span class="status-dot{{ count($credentials) > 0 ? ' on' : '' }}"></span>
                        {{ count($credentials) > 0 ? 'Enabled' : 'Not enabled' }}
                    </span>
                    <span class="bio-explainer" id="bioExplainer">
                        @if (count($credentials) > 0)
                            A biometric credential is registered for this account.
                        @else
                            No biometric credential is registered on this account or device.
                        @endif
                    </span>
                </div>

                <div id="biometricStatus" role="status" aria-live="polite"></div>
                <div id="biometricTestStatus" class="bio-msg" role="status" aria-live="polite" hidden></div>

                @if (count($credentials) > 0)
                    <ul class="cred-list" aria-label="Registered biometric devices">
                        @foreach ($credentials as $credential)
                            <li>
                                <div>
                                    <b>{{ $credential->name }}</b>
                                    <small>Added {{ $credential->created_at->diffForHumans() }}@if ($credential->last_used_at) &middot; last used {{ $credential->last_used_at->diffForHumans() }}@endif</small>
                                </div>
                                <button type="button" class="btn btn-outline btn-sm danger" data-delete-credential="{{ $credential->id }}">Remove</button>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="bio-actions">
                    @if (count($credentials) > 0)
                        <button type="button" class="btn btn-primary" id="testBiometric" data-bio-test>Test Biometric Login</button>
                    @endif
                    <button type="button" class="btn {{ count($credentials) > 0 ? 'btn-outline' : 'btn-primary' }}" id="enrollBiometric">@if (count($credentials) > 0) Add another device @else Enable Face / Biometric login @endif</button>
                </div>
            </div>

            {{-- Push notifications --}}
            <div class="card sec-card">
                <div class="sec-card-head">
                    <span class="sec-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </span>
                    <div class="sec-card-titles">
                        <h3 class="sec-card-title">Push notifications</h3>
                        <p class="sec-card-desc">Get browser notifications for billing reminders, announcements and filing updates &mdash; even when this tab is closed. You can turn them off anytime.</p>
                    </div>
                </div>

                <div class="push-actions">
                    <button type="button" id="pushToggleBtn" class="btn btn-primary push-toggle-btn" data-push-toggle data-push-state="disabled">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line class="bell-slash" x1="1" y1="1" x2="23" y2="23"/></svg>
                        <span class="push-toggle-label">Enable push notifications</span>
                    </button>
                    <button type="button" id="pushTestBtn" class="btn btn-outline" data-push-test hidden>
                        Test push
                    </button>
                </div>
            </div>

            {{-- Confidentiality --}}
            <div class="card sec-card">
                <div class="sec-card-head">
                    <span class="sec-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/><path d="m9 12 2 2 4-4"/></svg>
                    </span>
                    <div class="sec-card-titles">
                        <h3 class="sec-card-title">Confidentiality Policy</h3>
                        <p class="sec-card-desc">All client information and documents are strictly confidential. Review the full policy anytime.</p>
                    </div>
                </div>

                <div class="sec-actions">
                    <a href="{{ route('terms') }}" class="btn btn-outline" target="_blank">Read Terms &amp; Confidentiality</a>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-delete-credential]');
    if (!btn) return;
    var id = btn.getAttribute('data-delete-credential');
    egliane.confirm.action({ title: 'Remove this biometric login?', message: 'This device will no longer be able to sign in using Face / biometrics.', danger: true, confirmLabel: 'Remove' }, function () {
        fetch('/webauthn/credentials/' + id, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
        }).then(function (res) { return res.json(); }).then(function (data) {
            if (data.ok) { window.location.reload(); }
        });
    });
});

// Push toggle button: keep label/style in sync with subscription state (state itself is managed by push.js)
(function () {
    var btn = document.getElementById('pushToggleBtn');
    var testBtn = document.getElementById('pushTestBtn');
    function sync() {
        var on = !!btn && btn.getAttribute('data-push-state') === 'enabled';
        if (btn) {
            var label = btn.querySelector('.push-toggle-label');
            if (label) label.textContent = on ? 'Disable push notifications' : 'Enable push notifications';
            btn.classList.toggle('btn-primary', !on);
            btn.classList.toggle('btn-outline', on);
        }
        if (testBtn) testBtn.hidden = !on;
    }
    if (btn) {
        new MutationObserver(sync).observe(btn, { attributes: true, attributeFilter: ['data-push-state'] });
        // push.js resolves the real subscription state asynchronously after DOMContentLoaded
        window.addEventListener('load', function () { setTimeout(sync, 300); });
    }
})();

// Test push button: send an on-demand push to the current device only.
(function () {
    var testBtn = document.getElementById('pushTestBtn');
    if (!testBtn) return;
    testBtn.addEventListener('click', function () {
        if (testBtn.disabled) return;
        testBtn.disabled = true;
        fetch('/push/test', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                return { status: res.status, data: data };
            });
        }).then(function (r) {
            if (r.status === 429 && r.data.retryAfter) {
                window.egliane.toast('Please wait ' + r.data.retryAfter + 's before sending another test.');
            } else if (r.status === 403 || (r.data && r.data.sent === false)) {
                window.egliane.toast(r.data && r.data.message ? r.data.message : 'No push device found — enable push notifications first.');
            } else {
                window.egliane.toast('Test push sent — check your device.');
            }
        }).catch(function () {
            window.egliane.toast('Could not send test push. Please try again.');
        }).finally(function () {
            testBtn.disabled = false;
        });
    });
})();
// Test Biometric Login: run the existing WebAuthn assertion ceremony for the
// currently authenticated user only. It never signs anyone in, never creates a
// session for another account, never touches the PIN, and never modifies the stored
// credential — the server still verifies the assertion response before reporting success.
(function () {
    var testBtn = document.getElementById('testBiometric');
    var testStatus = document.getElementById('biometricTestStatus');
    if (!testBtn) return;

    var busy = false;

    function setMessage(msg, kind) {
        if (!testStatus) return;
        testStatus.textContent = msg || '';
        testStatus.className = 'bio-msg' + (kind ? ' bio-msg-' + kind : '');
        testStatus.hidden = !msg;
    }

    function setButton(label, disabled) {
        testBtn.disabled = !!disabled;
        testBtn.textContent = label || 'Test Biometric Login';
    }

    function postJson(url, payload) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify(payload)
        }).then(function (res) {
            return res.json().then(function (data) { return { status: res.status, data: data }; });
        });
    }

    testBtn.addEventListener('click', function () {
        if (busy || testBtn.disabled) return;
        busy = true;
        setMessage('', null);
        setButton('Waiting for biometric…', true);

        if (!window.egliane.webauthn.supported()) {
            setMessage('Your browser or device does not support biometric login.', 'error');
            setButton('Try again', false);
            busy = false;
            return;
        }

        fetch('/webauthn/test/options', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: '{}'
        })
            .then(function (res) { return res.json(); })
            .then(function (options) { return window.egliane.webauthn.get(options); })
            .then(function (credential) { return postJson('/webauthn/test/verify', { credential: credential }); })
            .then(function (res) {
                if (res.status === 200) {
                    setMessage('Biometric login is working on this device.', 'success');
                    setButton('Biometric login verified', false);
                } else {
                    throw { data: res.data };
                }
            })
            .catch(function (err) {
                var msg;
                if (err && err.data && err.data.error) {
                    msg = err.data.error;
                } else if (err && err.name === 'NotAllowedError') {
                    msg = 'Biometric verification was canceled.';
                } else if (err && err.name === 'NotSupportedError') {
                    msg = 'Your browser or device does not support biometric login.';
                } else if (err && (err.name === 'TimeoutError' || err.name === 'AbortError')) {
                    msg = 'Biometric verification timed out. Please try again.';
                } else {
                    msg = 'Biometric verification failed. Please try again.';
                }
                setMessage(msg, 'error');
                setButton('Try again', false);
            })
            .then(function () { busy = false; });
    });
})();

// Browser/device support guard: if WebAuthn is unavailable, disable the biometric
// actions instead of pretending an enrollment/test ceremony can run here.
(function () {
    var supported = typeof window.PublicKeyCredential !== 'undefined' &&
        typeof window.navigator !== 'undefined' && !!window.navigator.credentials;
    if (supported) return;
    var s = document.getElementById('biometricStatus');
    if (s) { s.textContent = "Biometric login isn't supported by this browser or device."; s.className = 'alert alert-error'; }
    var en = document.getElementById('enrollBiometric');
    if (en) en.disabled = true;
    var tb = document.getElementById('testBiometric');
    if (tb) { tb.disabled = true; tb.textContent = 'Biometric login unavailable'; }
})();

// After a successful registration the existing flow reloads the page to render the
// new credential; flip the visible status immediately too so the UI never lags truth.
(function () {
    var bioStatusEl = document.querySelector('[data-bio-status]');
    if (!bioStatusEl) return;
    window.addEventListener('egliane:biometric-enabled', function () {
        bioStatusEl.setAttribute('data-bio-status', 'enabled');
        var badge = document.getElementById('bioBadge');
        if (badge) { badge.classList.add('online'); badge.textContent = 'Enabled'; }
        var dot = badge ? badge.querySelector('.status-dot') : null;
        if (dot) dot.classList.add('on');
        var expl = document.getElementById('bioExplainer');
        if (expl) expl.textContent = 'A biometric credential is registered for this account.';
    });
})();
</script>
@endpush