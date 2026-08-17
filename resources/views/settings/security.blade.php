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
</script>
@endpush
