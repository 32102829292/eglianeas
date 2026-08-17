<!DOCTYPE html>
<html lang="en">
@include('layouts.head')
<body>
    <div class="offline-banner" id="offlineBanner">You&rsquo;re offline &mdash; showing previously loaded data.</div>

    @include('partials.header')

    <main>
        @yield('content')
    </main>

    @include('partials.footer')
    @include('partials.chatbot')

    <script src="/js/app.js" defer></script>
    @stack('scripts')
</body>
</html>
