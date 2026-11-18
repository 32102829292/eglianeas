
<div class="egliane-page-loader" id="pageLoader" aria-hidden="true">
    <div class="egliane-page-loader__widget" aria-hidden="true">
        <div class="egliane-page-loader__ring" aria-hidden="true"></div>
        <img class="egliane-page-loader__logo" src="<?php echo e(asset('images/logo-icon.png')); ?>" alt="" width="64" height="64">
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
</script><?php /**PATH C:\Users\USER\Documents\EAS\eglianeas\resources\views/components/page-loader.blade.php ENDPATH**/ ?>