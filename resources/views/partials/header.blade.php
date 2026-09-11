<header class="site-header">
    <div class="header-inner">
        <a href="{{ route('home') }}" class="brand" aria-label="Egliane Accounting Services — Home">
            <img src="{{ asset('images/logo-icon.png') }}" alt="" class="brand-logo">
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
                <a href="{{ route('login') }}" class="btn btn-outline btn-sm">Log In</a>
                <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Sign Up</a>
            @endauth
        </div>

        <button type="button" class="nav-toggle" id="navToggle" aria-label="Open menu" aria-expanded="false" aria-controls="mobileNav">
            <svg class="nav-toggle-icon nav-toggle-icon--open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            <svg class="nav-toggle-icon nav-toggle-icon--close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
    </div>

    <nav class="mobile-nav" id="mobileNav" aria-label="Mobile navigation">
        <a href="{{ route('home') }}#services">Services</a>
        <a href="{{ route('home') }}#about">About</a>
        <a href="{{ route('home') }}#contact">Contact</a>
        <a href="{{ route('help') }}">Help</a>
        @auth
            <a href="{{ auth()->user()->getDashboardRoute() }}" class="btn btn-primary">Go to Dashboard</a>
            <form method="POST" action="{{ route('logout') }}" class="inline-form" onsubmit="return egliane.confirm.form(this, { title: 'Log out?', message: 'You&rsquo;ll need to log in again to access your account.', confirmLabel: 'Log out' });">
                @csrf
                <button type="submit" class="btn btn-outline btn-block">Log out</button>
            </form>
        @else
            <a href="{{ route('login') }}" class="btn btn-outline btn-block">Log In</a>
            <a href="{{ route('register') }}" class="btn btn-primary btn-block">Sign Up</a>
        @endauth
    </nav>
</header>