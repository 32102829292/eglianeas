<header class="site-header">
    <div class="header-inner">
        <a href="{{ route('home') }}" class="brand">
            <img src="/images/logo-icon.png" alt="Egliane logo" class="brand-logo">
            <span class="brand-name">Egliane Accounting Services<small>Bookkeeping &middot; Tax &middot; Payroll</small></span>
        </a>

        <nav class="main-nav" aria-label="Main navigation">
            <a href="{{ route('home') }}#services">Services</a>
            <a href="{{ route('home') }}#about">About</a>
            <a href="{{ route('home') }}#contact">Contact</a>
            <a href="{{ route('help') }}">Help</a>
        </nav>

        <div class="nav-actions">
            @auth
                <a href="{{ auth()->user()->getDashboardRoute() }}" class="btn btn-primary btn-sm">Dashboard</a>
                <form method="POST" action="{{ route('logout') }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Log out?', message: 'You&rsquo;ll need to log in again to access your account.', confirmLabel: 'Log out' });">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">Log out</button>
                </form>
            @else
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Sign Up</a>
            @endauth
        </div>

        <button class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
    </div>

    <nav class="mobile-nav" id="mobileNav" aria-label="Mobile navigation">
        <a href="{{ route('home') }}#services">Services</a>
        <a href="{{ route('home') }}#about">About</a>
        <a href="{{ route('home') }}#contact">Contact</a>
        <a href="{{ route('help') }}">Help</a>
        @auth
            <a href="{{ auth()->user()->getDashboardRoute() }}" class="btn btn-primary">Go to Dashboard</a>
        @else
            <a href="{{ route('register') }}" class="btn btn-primary">Sign Up</a>
        @endauth
    </nav>
</header>
