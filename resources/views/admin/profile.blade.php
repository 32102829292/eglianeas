@php
    $user = auth()->user();
    $quickActions = [
        ['icon' => 'billing', 'label' => 'Billing statements', 'desc' => 'Create and manage periodic billing', 'url' => route('admin.billing.index')],
        ['icon' => 'collections', 'label' => 'Collections', 'desc' => 'Track payments and follow-ups', 'url' => route('admin.collections.index')],
        ['icon' => 'documents', 'label' => 'Document distribution', 'desc' => 'Review and distribute documents', 'url' => route('admin.distribution.index')],
        ['icon' => 'tracker', 'label' => 'Service tracker', 'desc' => 'See progress of tracked services', 'url' => route('admin.service-tracker.index')],
        ['icon' => 'security', 'label' => 'Security settings', 'desc' => 'Manage PIN and biometric login', 'url' => route('security.index')],
        ['icon' => 'help', 'label' => 'Help', 'desc' => 'Guides and how-to articles', 'url' => route('help')],
    ];
@endphp

@extends('layouts.dashboard')

@section('title', 'My Profile — Egliane Accounting Services')

@section('content')
    <div class="page-head">
        <span class="sec-eyebrow">Account dashboard</span>
        <h1>My profile</h1>
        <p>Your account details, activity, and sign-in security.</p>
    </div>

    <div class="profile-page">

        {{-- ============ PROFILE HEADER ============ --}}
        <div class="profile-hero">
            <span class="profile-hero-avatar avatar avatar-tint">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
            <div class="profile-hero-info">
                <h2 class="profile-hero-name">{{ $user->name }}</h2>
                <div class="profile-hero-email">{{ $user->email }}</div>
                <div class="profile-hero-meta">
                    <span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span>
                    <span class="profile-hero-since">Member since {{ $user->created_at->format('M j, Y') }}</span>
                </div>
                <p class="profile-hero-desc">
                    @if ($user->isAdmin())
                        Administrator account with full access to Egliane&rsquo;s management tools.
                    @else
                        Staff account with access to Egliane&rsquo;s client management tools.
                    @endif
                </p>
            </div>
            <div class="profile-hero-actions">
                @if (! $editMode)
                    <a href="{{ route('admin.profile.index', ['edit' => 1]) }}" class="btn btn-primary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
                        Edit Profile
                    </a>
                @endif
                <button type="button" class="btn btn-outline" data-onboarding-replay>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="10"/><path d="m16.24 7.76-2.12 6.36-6.36 2.12 2.12-6.36 6.36-2.12z"/></svg>
                    Take a tour
                </button>
            </div>
        </div>

        {{-- ============ ACCOUNT INFORMATION ============ --}}
        <div class="card">
            <div class="card-head">
                <div class="card-titles">
                    <span class="sec-eyebrow">Account</span>
                    <h3 class="card-title">Account information</h3>
                </div>
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
                    <div class="profile-row"><span class="profile-k">Role</span><span class="profile-v"><span class="badge badge-{{ $user->role }}">{{ ucfirst($user->role) }}</span></span></div>
                    <div class="profile-row"><span class="profile-k">Member since</span><span class="profile-v">{{ $user->created_at->format('M j, Y') }}</span></div>
                    @if ($user->business_name)
                        <div class="profile-row"><span class="profile-k">Business name</span><span class="profile-v">{{ $user->business_name }}</span></div>
                    @endif
                    @if ($user->relationLoaded('teamMember') && $user->teamMember)
                        @if ($user->teamMember->position)
                            <div class="profile-row"><span class="profile-k">Position</span><span class="profile-v">{{ $user->teamMember->position }}</span></div>
                        @endif
                        @if ($user->teamMember->department)
                            <div class="profile-row"><span class="profile-k">Department</span><span class="profile-v">{{ $user->teamMember->department }}</span></div>
                        @endif
                    @endif
                </div>
            @endif
        </div>

        {{-- ============ ACCOUNT ACTIVITY ============ --}}
        <div class="card">
            <div class="card-head">
                <div class="card-titles">
                    <span class="sec-eyebrow">Workspace</span>
                    <h3 class="card-title">Account activity</h3>
                </div>
            </div>
            <div class="account-metrics">
                <a href="{{ route('admin.distribution.index') }}" class="metric-card">
                    <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16v-2"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg></span>
                    <span class="metric-body">
                        <span class="metric-num">{{ number_format($metrics['documents']) }}</span>
                        <span class="metric-label">Documents</span>
                    </span>
                    <svg class="metric-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="{{ route('admin.billing.index') }}" class="metric-card">
                    <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg></span>
                    <span class="metric-body">
                        <span class="metric-num">{{ number_format($metrics['billings']) }}</span>
                        <span class="metric-label">Billing statements</span>
                    </span>
                    <svg class="metric-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                <a href="{{ route('admin.collections.index') }}" class="metric-card">
                    <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
                    <span class="metric-body">
                        <span class="metric-num">{{ number_format($metrics['pending']) }}</span>
                        <span class="metric-label">Pending items</span>
                    </span>
                    <svg class="metric-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                </a>
                @if (isset($metrics['assigned']) && $metrics['assigned'] > 0)
                    <a href="{{ route('admin.service-tracker.index') }}" class="metric-card">
                        <span class="metric-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg></span>
                        <span class="metric-body">
                            <span class="metric-num">{{ number_format($metrics['assigned']) }}</span>
                            <span class="metric-label">Assigned services</span>
                        </span>
                        <svg class="metric-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                @endif
            </div>
        </div>

        {{-- ============ QUICK ACTIONS ============ --}}
        <div class="card">
            <div class="card-head">
                <div class="card-titles">
                    <span class="sec-eyebrow">Shortcuts</span>
                    <h3 class="card-title">Quick actions</h3>
                </div>
            </div>
            <div class="quick-actions">
                @foreach ($quickActions as $action)
                    <a href="{{ $action['url'] }}" class="qa-item">
                        <span class="qa-icon">
                            @if ($action['icon'] === 'billing')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg>
                            @elseif ($action['icon'] === 'collections')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6 12h4l2 3 4-6h2"/></svg>
                            @elseif ($action['icon'] === 'documents')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16v-2"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
                            @elseif ($action['icon'] === 'tracker')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
                            @elseif ($action['icon'] === 'security')
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            @endif
                        </span>
                        <span class="qa-info">
                            <b>{{ $action['label'] }}</b>
                            <small>{{ $action['desc'] }}</small>
                        </span>
                        <svg class="qa-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                @endforeach
            </div>
        </div>

        {{-- ============ LOGIN & SECURITY + PRIVACY ============ --}}
        <div class="profile-duo">
            <div class="card sec-summary">
                <div class="card-head">
                    <div class="card-titles">
                        <span class="sec-eyebrow">Security</span>
                        <h3 class="card-title">Login &amp; security</h3>
                    </div>
                </div>

                <p class="card-sub">
                    @if ($user->hasPin() && count($credentials) > 0)
                        Your account uses a PIN and supported device authentication for secure sign-in.
                    @elseif ($user->hasPin())
                        Your account uses a PIN for secure sign-in. Add supported device authentication for even faster access.
                    @elseif (count($credentials) > 0)
                        Your account uses supported device authentication for secure sign-in. Add a PIN for another sign-in option.
                    @else
                        Add a PIN and supported device authentication for secure sign-in. Manage them from the Security settings page.
                    @endif
                </p>

                <div class="sec-rows">
                    <div class="sec-row">
                        <span class="sec-row-k">PIN login</span>
                        <span class="sec-row-v">
                            @if ($user->hasPin())
                                <span class="sec-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="12" height="12" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                                <span>Set</span>
                            @else
                                <span class="sec-dash" aria-hidden="true">&mdash;</span>
                                <span>Not set</span>
                            @endif
                        </span>
                    </div>
                    <div class="sec-row">
                        <span class="sec-row-k">Face / Biometric</span>
                        <span class="sec-row-v">
                            @if (count($credentials) > 0)
                                <span class="sec-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="12" height="12" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                                <span>Enabled</span>
                            @else
                                <span class="sec-dash" aria-hidden="true">&mdash;</span>
                                <span>Not enabled</span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="sec-summary-cta">
                    <a href="{{ route('security.index') }}" class="btn btn-primary">
                        Security settings
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </a>
                </div>
            </div>

            <div class="card privacy-card">
                <div class="card-head">
                    <div class="card-titles">
                        <span class="sec-eyebrow">Privacy</span>
                        <h3 class="card-title">Privacy &amp; confidentiality</h3>
                    </div>
                </div>

                <p class="card-sub">Your confidentiality agreement and the terms you have agreed to.</p>

                <div class="sec-rows">
                    <div class="sec-row">
                        <span class="sec-row-k">Confidentiality acknowledgment</span>
                        <span class="sec-row-v">
                            @if ($user->confidentiality_acknowledged_at)
                                <span class="sec-check" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="12" height="12" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg></span>
                                <span>Acknowledged</span>
                            @else
                                <span class="sec-dash" aria-hidden="true">&mdash;</span>
                                <span>Not yet acknowledged</span>
                            @endif
                        </span>
                    </div>
                </div>

                <div class="privacy-links">
                    <a href="{{ route('terms') }}" class="btn btn-outline" target="_blank" rel="noopener">
                        View Terms &amp; Confidentiality
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><path d="M15 3h6v6"/><path d="M10 14 21 3"/></svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- ============ RECENT ACTIVITY ============ --}}
        @if ($activities->isNotEmpty())
            <div class="card">
                <div class="card-head">
                    <div class="card-titles">
                        <span class="sec-eyebrow">Activity</span>
                        <h3 class="card-title">Recent activity</h3>
                    </div>
                </div>
                <div class="activity-timeline">
                    @foreach ($activities as $activity)
                        @php
                            $time = $activity->created_at;
                            $stamp = $time->isToday() ? 'Today' : ($time->isYesterday() ? 'Yesterday' : $time->format('M j, Y'));
                            $text = $activity->description ?: ucfirst(str_replace('_', ' ', $activity->action));
                        @endphp
                        <div class="act-item">
                            <span class="act-dot" aria-hidden="true"></span>
                            <div class="act-body">
                                <span class="act-text">{{ $text }}</span>
                                <span class="act-time">{{ $stamp }} &middot; {{ $time->format('g:i A') }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@endsection