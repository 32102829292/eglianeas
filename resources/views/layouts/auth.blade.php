<!DOCTYPE html>
<html lang="en">
@include('layouts.head')
<body class="auth-body">
    <div class="offline-banner" id="offlineBanner">You&rsquo;re offline &mdash; showing previously loaded data.</div>

    <div class="auth-card {{ $cardClass ?? '' }}">
        <a href="{{ route('home') }}" class="auth-brand">
            <img src="/images/logo-icon.png" alt="Egliane Accounting Services logo" style="width:120px;height:auto;border-radius:0;">
        </a>

        @yield('content')
        {{ $slot ?? '' }}
    </div>

    <script src="/js/app.js?v=4" defer></script>
    <script src="/js/auth.js?v=2" defer></script>
    @stack('scripts')
</body>
</html>
