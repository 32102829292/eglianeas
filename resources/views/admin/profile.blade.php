@php
    $user = auth()->user();
@endphp

@extends('layouts.dashboard')

@section('title', 'My Profile — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <h1>My profile</h1>
        <p>Your account details and sign-in security.</p>
    </div>

    <div class="card">
        <div class="card-head">
            <h3 class="card-title">Account information</h3>
            @if (! $editMode)
                <a href="{{ route('admin.profile.index', ['edit' => 1]) }}" class="btn btn-outline btn-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                    Edit
                </a>
            @endif
        </div>

        @if ($editMode)
            <form method="POST" action="{{ route('admin.profile.update') }}">
                @csrf
                @method('PATCH')
                <div class="form-grid two">
                    <div class="form-group">
                        <label class="form-label" for="name">Full name</label>
                        <input class="form-control" id="name" type="text" name="name" value="{{ old('name', $user->name) }}" required maxlength="255">
                        @error('name')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" type="email" name="email" value="{{ old('email', $user->email) }}" required maxlength="255">
                        @error('email')<div class="form-error">{{ $message }}</div>@enderror
                    </div>
                </div>
                <div class="btn-group-row">
                    <button type="submit" class="btn btn-primary">Save changes</button>
                    <a href="{{ route('admin.profile.index') }}" class="btn btn-outline">Cancel</a>
                </div>
            </form>
        @else
            <div class="profile-grid">
                <div class="profile-row"><span class="profile-k">Full name</span><span class="profile-v">{{ $user->name }}</span></div>
                <div class="profile-row"><span class="profile-k">Email</span><span class="profile-v">{{ $user->email }}</span></div>
                <div class="profile-row"><span class="profile-k">Role</span><span class="profile-v"><span class="badge badge-neutral">{{ ucfirst($user->role) }}</span></span></div>
                <div class="profile-row"><span class="profile-k">Member since</span><span class="profile-v">{{ $user->created_at->format('M j, Y') }}</span></div>
            </div>
        @endif
    </div>

    <div class="grid-2">
        <div class="card">
            <h3 class="card-title">PIN login</h3>
            <p class="card-sub">{{ $user->hasPin() ? 'Your PIN is set. Update it anytime.' : 'Set a 4-digit PIN to log in without typing your password.' }}</p>
            <form method="POST" action="{{ route('security.pin') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="current_password">Current password or PIN</label>
                    <input class="form-control" id="current_password" type="password" name="current_password" autocomplete="current-password">
                    <small class="muted">Required only if your account already has a password or PIN.</small>
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
</script>
@endpush
