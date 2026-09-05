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
    @include('components.confirm-modal')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
    <script src="/js/confirm.js?v=1" defer></script>
    <script src="/js/app.js?v=4" defer></script>
    @stack('scripts')
</body>
</html>
