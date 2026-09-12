<!DOCTYPE html>
<html lang="en">
@include('layouts.head')
<body class="auth-body">
    <div class="offline-banner" id="offlineBanner">You&rsquo;re offline &mdash; showing previously loaded data.</div>

    @hasSection('login-shell')
        @yield('login-shell')
    @else
        <div class="auth-card {{ $cardClass ?? '' }}">
            <a href="{{ route('home') }}" class="auth-brand">
                <img src="/images/logo-icon.png" alt="Egliane Accounting Services logo">
            </a>

            @yield('content')
            {{ $slot ?? '' }}
        </div>
    @endif

    <script src="/js/app.js?v=9" defer></script>
    <script src="/js/auth.js?v=3" defer></script>
    @stack('scripts')
</body>
</html>
