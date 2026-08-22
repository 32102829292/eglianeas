@extends('layouts.dashboard')

@section('title', 'Security — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>Security settings</h1>
        <p>Add an extra layer of convenience: log in with your PIN or your face.</p>
    </div>

    <div class="grid-2">
        {{-- PIN --}}
        <div class="card">
            <h3 class="card-title">PIN login</h3>
            <p class="card-sub">{{ $user->hasPin() ? 'Your PIN is set. Update it anytime.' : 'Set a 4-digit PIN to log in without typing your password.' }}</p>
            <form method="POST" action="{{ route('security.pin') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="current_password">Current password</label>
                    <input class="form-control" id="current_password" type="password" name="current_password" required autocomplete="current-password">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pin">PIN</label>
                    <input class="form-control" id="pin" type="password" name="pin" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder="4 digits">
                </div>
                <div class="form-group">
                    <label class="form-label" for="pin_confirmation">Confirm PIN</label>
                    <input class="form-control" id="pin_confirmation" type="password" name="pin_confirmation" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" required placeholder="Repeat your PIN">
                </div>
                <button type="submit" class="btn btn-primary">{{ $user->hasPin() ? 'Update PIN' : 'Set PIN' }}</button>
            </form>
        </div>

        {{-- Biometric --}}
        <div class="card">
            <h3 class="card-title">Face / Biometric login</h3>
            <p class="card-sub">Use your device&rsquo;s Face ID or Windows Hello to sign in in one touch. Face data stays on your device.</p>
            <div id="biometricStatus"></div>

            @if (count($credentials) > 0)
                <ul class="cred-list">
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
            @else
                <p class="muted">No biometric credentials registered yet.</p>
            @endif

            <button type="button" class="btn btn-primary mt-2" id="enrollBiometric">Enable Face / Biometric login</button>
        </div>

        {{-- Push notifications --}}
        <div class="card">
            <h3 class="card-title">Push notifications</h3>
            <p class="card-sub">Get browser notifications for billing reminders, announcements and filing updates &mdash; even when this tab is closed. You can turn them off anytime.</p>
            <button type="button" id="pushToggleBtn" class="btn btn-primary push-toggle-btn mt-2" data-push-toggle data-push-state="disabled">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/><line class="bell-slash" x1="1" y1="1" x2="23" y2="23"/></svg>
                <span class="push-toggle-label">Enable push notifications</span>
            </button>
        </div>

        <div class="card">
            <h3 class="card-title">Confidentiality Policy</h3>
            <p class="card-sub">All client information and documents are strictly confidential. Review the full policy anytime.</p>
            <a href="{{ route('terms') }}" class="btn btn-outline" target="_blank">Read Terms &amp; Confidentiality</a>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-delete-credential]');
    if (!btn) return;
    if (!confirm('Remove this biometric login?')) return;
    var id = btn.getAttribute('data-delete-credential');
    fetch('/webauthn/credentials/' + id, {
        method: 'DELETE',
        headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content') }
    }).then(function (res) { return res.json(); }).then(function (data) {
        if (data.ok) { window.location.reload(); }
    });
});

// Push toggle button: keep label/style in sync with subscription state (state itself is managed by push.js)
(function () {
    var btn = document.getElementById('pushToggleBtn');
    if (!btn) return;
    var label = btn.querySelector('.push-toggle-label');
    function sync() {
        var on = btn.getAttribute('data-push-state') === 'enabled';
        if (label) label.textContent = on ? 'Disable push notifications' : 'Enable push notifications';
        btn.classList.toggle('btn-primary', !on);
        btn.classList.toggle('btn-outline', on);
    }
    new MutationObserver(sync).observe(btn, { attributes: true, attributeFilter: ['data-push-state'] });
    // push.js resolves the real subscription state asynchronously after DOMContentLoaded
    window.addEventListener('load', function () { setTimeout(sync, 300); });
})();
</script>
@endpush
