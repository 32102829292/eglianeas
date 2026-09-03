@php
    use Illuminate\Support\Str;
    $user = auth()->user();
    $routeName = request()->route() ? request()->route()->getName() : '';
    $active = function (string $prefix) use ($routeName): string {
        return Str::startsWith($routeName, $prefix) ? 'active' : '';
    };
    $unreadCount = $user->unreadNotificationsCount();
    $recentNotifications = $user->notifications()->latest()->limit(8)->get();
@endphp
<!DOCTYPE html>
<html lang="en">
@include('layouts.head')
<body class="dash-body">
    @include('partials.impersonation-banner')
    <div class="offline-banner" id="offlineBanner">You&rsquo;re offline &mdash; showing previously loaded data.</div>

    <div class="dash-topbar">
        <div class="dash-topbar-inner">
            <button class="hamburger" id="hamburgerBtn" aria-label="Toggle navigation" aria-expanded="false">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
            <a href="{{ route('home') }}" class="brand">
                <img src="/images/logo-icon.png" alt="Egliane logo" class="brand-logo">
                <span class="brand-name">Egliane<small>Accounting Services</small></span>
            </a>
            <div class="dash-topbar-right">
                <div class="dash-bell" id="bellWrap">
                    <button type="button" class="bell-btn" id="bellBtn" aria-label="Notifications" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        @if ($unreadCount > 0)
                            <span class="bell-badge">{{ min($unreadCount, 99) }}</span>
                        @endif
                    </button>
                    <div class="bell-dropdown" id="bellDropdown">
                        <div class="bell-head">
                            <span>Notifications
                                @if ($unreadCount > 0)<span class="bell-head-count">{{ min($unreadCount, 99) }}</span>@endif
                            </span>
                            @if ($unreadCount > 0)
                                <form method="POST" action="{{ route('notifications.read-all') }}" class="inline-form">
                                    @csrf
                                    <button type="submit" class="link-btn">Mark all read</button>
                                </form>
                            @endif
                        </div>
                        <div class="bell-list">
                            @forelse ($recentNotifications as $notification)
                                <a href="{{ route('notifications.open', $notification) }}" class="bell-item @if ($notification->isUnread()) unread @endif">
                                    <div class="bell-item-title">{{ $notification->title }}</div>
                                    @if ($notification->body)
                                        <div class="bell-item-body">{{ Str::limit($notification->body, 90) }}</div>
                                    @endif
                                    @if (($notification->reminder_count ?? 1) > 1)
                                        <div class="reminder-count">Reminded {{ $notification->reminder_count }} times</div>
                                    @endif
                                    <div class="bell-item-time">{{ $notification->created_at->diffForHumans() }}</div>
                                </a>
                            @empty
                                <div class="bell-empty">No notifications yet.</div>
                            @endforelse
                        </div>
                        <a href="{{ route('notifications.index') }}" class="bell-footer">View all notifications</a>
                    </div>
                </div>
                <a href="{{ route('help') }}" class="dash-help-btn" title="How to use this system" aria-label="Help guide">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                </a>
                <div class="dash-user-chip">
                    <span class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                    <span class="hidden-xs">{{ $user->name }}</span>
                    <span class="dash-role">{{ ucfirst($user->role) }}</span>
                </div>
                <button type="button" class="btn btn-outline-light btn-sm hidden" data-install>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px;margin-right:4px;vertical-align:-2px"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Install
                </button>
                <div class="ios-install-tip hidden" id="iosInstallTip" role="tooltip" aria-hidden="true">
                    <button type="button" class="ios-install-tip-close" id="iosInstallTipClose" aria-label="Dismiss">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                    <strong>Install the app</strong>
                    <span>Tap the <svg class="share-glyph" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l-4 5h2v7h4V7h2l-4-5zM5 10H3v11h18V10h-2v9H5v-9z"/></svg> Share icon, then &ldquo;Add to Home Screen&rdquo;.</span>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Log out?', message: 'You&rsquo;ll need to log in again to access your account.', confirmLabel: 'Log out' });">
                    @csrf
                    <button type="submit" class="btn btn-outline-light btn-sm">Log out</button>
                </form>
            </div>
        </div>
    </div>

    <div class="dash-drawer-backdrop" id="dashDrawerBackdrop"></div>
    <nav class="dash-drawer" id="dashDrawer" aria-hidden="true">
        <div class="dash-drawer-head">
            <a href="{{ route('home') }}" class="brand">
                <img src="/images/logo-icon.png" alt="Egliane logo" class="brand-logo">
                <span class="brand-name">Egliane<small>Accounting Services</small></span>
            </a>
            <button type="button" class="drawer-close" id="drawerClose" aria-label="Close menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        @include('partials.dashboard-nav')
    </nav>

    <div class="dash-layout">
        <aside class="dash-nav">
            @include('partials.dashboard-nav')
        </aside>

        <main class="dash-main">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert-error">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div class="alert alert-error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif
            @yield('content')
            {{ $slot ?? '' }}
        </main>
    </div>

    @include('partials.chatbot')

    @include('components.confirm-modal')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="/js/confirm.js?v=1" defer></script>
    <script src="/js/app.js?v=3" defer></script>
    <script src="/js/auth.js?v=2" defer></script>
    <script src="/js/push.js?v=2" defer></script>
    @stack('scripts')
</body>
</html>
