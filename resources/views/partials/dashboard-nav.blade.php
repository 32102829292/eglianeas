@if ($user->isOperational())
    <style>
        .nav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.25rem;
            height: 1.25rem;
            padding: 0 0.375rem;
            font-size: 0.6875rem;
            font-weight: 700;
            line-height: 1;
            color: #fff;
            background-color: var(--danger, #dc2626);
            border-radius: 9999px;
            margin-left: 0.5rem;
            vertical-align: middle;
        }
        .dash-nav a .nav-badge {
            transition: background-color 0.2s, transform 0.1s;
        }
        .dash-nav a.active .nav-badge {
            background-color: var(--primary, #6366f1);
        }
    </style>
    <div class="dash-nav-head">Navigation</div>
    <a href="{{ route('admin.dashboard') }}" class="{{ $active('admin.dashboard') ?: ($routeName === 'dashboard' ? 'active' : '') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        Dashboard
    </a>
    <a href="{{ route('admin.profile.index') }}" class="{{ $active('admin.profile') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
    </a>
    <a href="{{ route('admin.announcements.index') }}" class="{{ $active('admin.announcements') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11v2a1 1 0 0 0 1 1h2l4 4V6l-4 4H4a1 1 0 0 0-1 1z"/><path d="M14 8a5 5 0 0 1 0 8"/><path d="M17 5a9 9 0 0 1 0 14"/></svg>
        Announcements
    </a>
    <a href="{{ route('security.index') }}" class="{{ $active('security') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
        Security Settings
    </a>
    <a href="{{ route('admin.chatbot') }}" class="{{ $active('admin.chatbot') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Chatbot
    </a>

    @php
        $missingJournalCount = \App\Services\DailyJournalService::getMissingCountForUser($user->id);
        $dailyJournalActive = $active('daily-journal') || $active('daily-journal.admin') || $active('daily-journal.supervisor');
    @endphp
    <a href="{{ route('daily-journal.create') }}" class="{{ $dailyJournalActive }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
        Daily Accomplishment
        @if ($missingJournalCount > 0)
            <span class="nav-badge">{{ $missingJournalCount }}</span>
        @endif
    </a>

    <div class="dash-nav-head">Clients</div>
    <a href="{{ route('admin.clients.index') }}" class="{{ $active('admin.clients') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Client List
    </a>
    @if ($user->isAdmin())
        <a href="{{ route('admin.clients.pending') }}" class="{{ $active('admin.clients.pending') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M17 11l2 2 4-4"/></svg>
            Pending Accounts
            @if (($pendingClientCount ?? 0) > 0)
                <span class="nav-badge">{{ min($pendingClientCount, 99) }}</span>
            @endif
        </a>
    @endif
    <a href="{{ route('admin.billing.index') }}" class="{{ $active('admin.billing') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg>
        Billing Statements
    </a>
    <a href="{{ route('admin.collections.index') }}" class="{{ $active('admin.collections') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6 12h4l2 3 4-6h2"/></svg>
        Collections &amp; Follow-ups
    </a>
    <a href="{{ route('admin.surveys.index') }}" class="{{ $active('admin.surveys') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>
        Client Feedback
    </a>
    <a href="{{ route('admin.distribution.index') }}" class="{{ $active('admin.distribution') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16v-2"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
        Document Distribution
    </a>
    <a href="{{ route('admin.bir-forms.index') }}" class="{{ $active('admin.bir-forms') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        BIR Forms
    </a>

    <div class="dash-nav-head">Other Services</div>
    <a href="{{ route('admin.other-services.fill-up') }}" class="{{ $active('admin.other-services.fill-up') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>
        Fill Up Form
    </a>
    <a href="{{ route('admin.other-services.billing') }}" class="{{ $active('admin.other-services.billing') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg>
        Billing Statements
    </a>
    <a href="{{ route('admin.other-services.collections') }}" class="{{ $active('admin.other-services.collections') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6 12h4l2 3 4-6h2"/></svg>
        Collections &amp; Follow-ups
    </a>
    <a href="{{ route('admin.service-tracker.index') }}" class="{{ $active('admin.service-tracker') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Service Tracker
    </a>
    <a href="{{ route('admin.service-tracker.concerns') }}" class="{{ $active('admin.service-tracker.concerns') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
        Client Concerns
    </a>

    <div class="dash-nav-head">Kaizen & Priorities</div>
    <a href="{{ route('admin.kaizen-concerns.index') }}" class="{{ $active('admin.kaizen-concerns') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
        Admin Concerns / Kaizen Strategy
    </a>
    <a href="{{ route('admin.priority-items.index') }}" class="{{ $active('admin.priority-items') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Priority List / To-Do List
    </a>

    @if ($user->isOperational())
        <a href="{{ route('admin.weekly-bookkeeping.index') }}" class="{{ $active('admin.weekly-bookkeeping') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 14h2M9 18h2"/></svg>
            Weekly Bookkeeping
        </a>
        <a href="{{ route('admin.monthly-bookkeeping.index') }}" class="{{ $active('admin.monthly-bookkeeping') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="M9 14h2M9 18h2M15 14h2M15 18h2"/></svg>
            Monthly Bookkeeping
        </a>
        <a href="{{ route('admin.quarterly-bookkeeping.index') }}" class="{{ $active('admin.quarterly-bookkeeping') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-5 3 3 5-7"/></svg>
            Quarterly Bookkeeping
        </a>
    @endif

    <div class="dash-nav-head">System</div>
    @if ($user->isAdmin())
        <a href="{{ route('admin.activity-logs') }}" class="{{ $active('admin.activity') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
            Activity Logs
        </a>
    @endif
    @if ($user->isAdmin() || $user->isSupervisor())
        <a href="{{ route('admin.users.index') }}" class="{{ $active('admin.users') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M17 11l2 2 4-4"/></svg>
            Team Accounts
        </a>
    @endif
    <a href="{{ route('admin.about') }}" class="{{ $active('admin.about') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
        About
    </a>

    <div class="nav-support-card">
        <div class="nav-support-head">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            <span>Need help?</span>
        </div>
        <p>Ask the built-in assistant for guidance around the admin panel.</p>
        <a href="{{ route('admin.chatbot') }}" class="btn btn-sm btn-outline">Open assistant</a>
    </div>

    <div class="dash-nav-conf">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span>Confidential &mdash; do not share client data</span>
    </div>
    <a href="{{ route('about.public') }}" class="dash-nav-link-sub">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
        About Egliane
    </a>
@elseif ($user->isClient() && ! $user->isAccountApproved())
    <div class="dash-nav-head">Navigation</div>
    <a href="{{ route('client.pending-approval') }}" class="active">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 7v5l3 3"/></svg>
        Account Status
    </a>
    <a href="{{ route('security.index') }}" class="{{ $active('security') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
        Security Settings
    </a>
    <a href="{{ route('notifications.index') }}" class="{{ $active('notifications') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
    </a>
    <a href="{{ route('about.public') }}" class="dash-nav-link-sub">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
        About Egliane
    </a>
@else
    <div class="dash-nav-head">Navigation</div>
    <a href="{{ route('client.dashboard') }}" class="{{ $active('client.dashboard') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        Dashboard
    </a>
    <a href="{{ route('security.index') }}" class="{{ $active('security') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
        Security Settings
    </a>

    <div class="dash-nav-head">My Account</div>
    <a href="{{ route('client.profile.edit') }}" class="{{ $active('client.profile') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        Profile
    </a>
    <a href="{{ route('client.billing.index') }}" class="{{ $active('client.billing') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg>
        Billing Statements
    </a>
    <a href="{{ route('client.collections.index') }}" class="{{ $active('client.collections') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6 12h4l2 3 4-6h2"/></svg>
        Collections &amp; Follow-ups
    </a>
    <a href="{{ route('client.documents.index') }}" class="{{ $active('client.documents') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16v-2"/><path d="M3.27 6.96 12 12.01l8.73-5.05"/><path d="M12 22.08V12"/></svg>
        Documents
    </a>

    <div class="dash-nav-head">Other Services</div>
    <a href="{{ route('client.other-services.billing') }}" class="{{ $active('client.other-services.billing') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2h12a1 1 0 0 1 1 1v18a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V3a1 1 0 0 1 1-1z"/><path d="M9 6h6M9 10h6M9 14h6"/></svg>
        Billing Statements
    </a>
    <a href="{{ route('client.other-services.collections') }}" class="{{ $active('client.other-services.collections') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M6 12h4l2 3 4-6h2"/></svg>
        Collections &amp; Follow-ups
    </a>
    <a href="{{ route('client.service-tracker.index') }}" class="{{ $active('client.service-tracker') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11"/></svg>
        Service Tracker
    </a>
    <a href="{{ route('client.service-tracker.concerns') }}" class="{{ $active('client.service-tracker.concerns') }}">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
        Concerns
    </a>

    <a href="{{ route('about.public') }}" class="dash-nav-link-sub">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4m0-4h.01"/></svg>
        About Egliane
    </a>
@endif
