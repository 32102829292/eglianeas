{{--
    Brand page loader. Shared by the site, auth and dashboard layouts.
    - Visible from first paint (tiny inline script flips it on before app.js runs),
      hidden again by the page-loader module in app.js once the page is ready.
    - pointer-events: none — it can never block interaction.
    - Purely decorative (aria-hidden); the status text is announced politely.
    - No JS? The inline script never runs, so the loader stays display:none.
--}}
<div class="egliane-page-loader" id="pageLoader" aria-hidden="true">
    <div class="egliane-page-loader__widget" aria-hidden="true">
        <div class="egliane-page-loader__ring" aria-hidden="true"></div>
        <img class="egliane-page-loader__logo" src="{{ asset('images/logo-icon.png') }}" alt="" width="64" height="64">
    </div>
    <div class="egliane-page-loader__text">
        <div class="egliane-page-loader__title">Egliane Accounting Services</div>
        <div class="egliane-page-loader__sub" role="status" aria-live="polite">Loading&hellip;</div>
    </div>
</div>
<script>
    /* Show the brand loader from first paint; app.js fades it out when ready.
       Failsafe: never leave it up longer than 8s even if app.js never runs. */
    document.documentElement.classList.add('egliane-page-loader-active');
    setTimeout(function () {
        document.documentElement.classList.remove('egliane-page-loader-active');
    }, 8000);
</script>